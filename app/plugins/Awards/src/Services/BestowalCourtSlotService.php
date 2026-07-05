<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\TimezoneHelper;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Court slots tie a bestowal to a scheduled activity on the gathering's Event Schedule,
 * or to roaming court when the award may be given at any time during the event.
 */
class BestowalCourtSlotService
{
    /** Form/API value for the roaming court dropdown option. */
    public const ROAMING_COURT_VALUE = 'roaming';

    /**
     * Whether court session selection is available for a gathering.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @return bool
     */
    public function gatheringSupportsCourtSlots(?int $gatheringId): bool
    {
        return $gatheringId !== null && $gatheringId > 0;
    }

    /**
     * @param int|null $gatheringId Gathering primary key.
     * @return int
     */
    public function countScheduledActivities(?int $gatheringId): int
    {
        if ($gatheringId === null || $gatheringId <= 0) {
            return 0;
        }

        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');

        return (int)$activitiesTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->count();
    }

    /**
     * Build select options for court slot dropdowns.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @param int|null $selectedActivityId Currently selected scheduled activity.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @param bool $roamingCourtSelected Whether roaming court is the current selection.
     * @return array<int|string, string> Option value => label
     */
    public function buildOptions(
        ?int $gatheringId,
        ?int $selectedActivityId = null,
        $member = null,
        bool $roamingCourtSelected = false,
    ): array {
        if (!$this->gatheringSupportsCourtSlots($gatheringId)) {
            return [];
        }

        $options = [
            self::ROAMING_COURT_VALUE => self::roamingCourtLabel(),
        ];

        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $activities = $activitiesTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->orderBy(['start_datetime' => 'ASC'])
            ->all();

        foreach ($activities as $activity) {
            $options[(int)$activity->id] = $this->buildActivityLabel($activity, $member);
        }

        if ($selectedActivityId !== null && !isset($options[$selectedActivityId])) {
            try {
                $activity = $activitiesTable->get($selectedActivityId);
                $options[$selectedActivityId] = $this->buildActivityLabel($activity, $member);
            } catch (Throwable) {
            }
        }

        if ($roamingCourtSelected && !isset($options[self::ROAMING_COURT_VALUE])) {
            $options = [self::ROAMING_COURT_VALUE => self::roamingCourtLabel()] + $options;
        }

        return $options;
    }

    /**
     * Build court assignment options limited to sessions that can give the bestowal award.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return array<int|string, string> Option value => label.
     */
    public function buildEligibleOptionsForBestowal(Bestowal $bestowal, $member = null): array
    {
        $gatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        if (!$this->gatheringSupportsCourtSlots($gatheringId)) {
            return [];
        }

        $options = [
            self::ROAMING_COURT_VALUE => self::roamingCourtLabel(),
        ];
        foreach ($this->fetchEligibleScheduledActivities($bestowal) as $activity) {
            $options[(int)$activity->id] = $this->buildActivityLabel($activity, $member);
        }

        return $options;
    }

    /**
     * Whether the bestowal already satisfies the Added to Agenda court assignment requirement.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return bool
     */
    public function hasAgendaAssignment(Bestowal $bestowal): bool
    {
        return $this->isRoamingCourt($bestowal)
            || (int)($bestowal->gathering_scheduled_activity_id ?? 0) > 0;
    }

    /**
     * Apply a to-do court assignment, accepting only roaming or award-eligible scheduled activities.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param mixed $rawActivityId Submitted court assignment value.
     * @return void
     */
    public function applyEligibleCourtSessionSelection(Bestowal $bestowal, mixed $rawActivityId): void
    {
        if ($rawActivityId === null || $rawActivityId === '') {
            throw new RuntimeException(__('Choose Roaming Court or an eligible scheduled court activity.'));
        }

        if ((string)$rawActivityId === self::ROAMING_COURT_VALUE) {
            $this->applyCourtSessionSelection($bestowal, self::ROAMING_COURT_VALUE);

            return;
        }

        if (!ctype_digit((string)$rawActivityId)) {
            throw new RuntimeException(__('Choose Roaming Court or an eligible scheduled court activity.'));
        }

        $activityId = (int)$rawActivityId;
        if ($activityId <= 0 || !$this->scheduledActivityCanGiveBestowalAward($bestowal, $activityId)) {
            throw new RuntimeException(__('That scheduled activity cannot give this award.'));
        }

        $this->applyCourtSessionSelection($bestowal, $activityId);
    }

    /**
     * Check whether a scheduled activity belongs to the bestowal gathering and can give its award.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param int $scheduledActivityId Scheduled gathering activity id.
     * @return bool
     */
    public function scheduledActivityCanGiveBestowalAward(Bestowal $bestowal, int $scheduledActivityId): bool
    {
        $gatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        $awardId = $bestowal->award_id !== null ? (int)$bestowal->award_id : null;
        if ($gatheringId === null || $gatheringId <= 0 || $awardId === null || $awardId <= 0) {
            return false;
        }

        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $activity = $activitiesTable->find()
            ->select(['id', 'gathering_id', 'gathering_activity_id'])
            ->where([
                'id' => $scheduledActivityId,
                'gathering_id' => $gatheringId,
            ])
            ->first();
        if ($activity === null || (int)($activity->gathering_activity_id ?? 0) <= 0) {
            return false;
        }

        return TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities')->find()
            ->where([
                'award_id' => $awardId,
                'gathering_activity_id' => (int)$activity->gathering_activity_id,
            ])
            ->count() > 0;
    }

    /**
     * Build the initial court slot data needed by edit forms in one pass.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @param string|int|null $selectedActivityId Selected scheduled activity or roaming value.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @param bool $roamingCourtSelected Whether roaming court is the current selection.
     * @return array{
     *   options: array<int|string, string>,
     *   available: bool,
     *   hasScheduledSessions: bool,
     *   optionDates: array<int|string, string>,
     *   gatheringStartDate: string|null,
     *   suggestedBestowedDate: string|null
     * }
     */
    public function buildInitialFormData(
        ?int $gatheringId,
        int|string|null $selectedActivityId = null,
        $member = null,
        bool $roamingCourtSelected = false,
    ): array {
        if (!$this->gatheringSupportsCourtSlots($gatheringId)) {
            return [
                'options' => [],
                'available' => false,
                'hasScheduledSessions' => false,
                'optionDates' => [],
                'gatheringStartDate' => null,
                'suggestedBestowedDate' => null,
            ];
        }

        $activities = $this->fetchScheduledActivities($gatheringId, $selectedActivityId);
        $gatheringStartDate = $this->findGatheringStartDateYmd($gatheringId);
        $options = [
            self::ROAMING_COURT_VALUE => self::roamingCourtLabel(),
        ];
        $optionDates = [
            self::ROAMING_COURT_VALUE => $gatheringStartDate ?? '',
        ];

        foreach ($activities as $activity) {
            $options[(int)$activity->id] = $this->buildActivityLabel($activity, $member);
            if ($activity->start_datetime === null) {
                continue;
            }
            $ymd = $this->courtSessionDateYmd($activity->start_datetime, $member);
            if ($ymd !== '') {
                $optionDates[(int)$activity->id] = $ymd;
            }
        }
        $hasScheduledSessions = false;
        foreach ($activities as $activity) {
            if ((int)$activity->gathering_id === $gatheringId) {
                $hasScheduledSessions = true;
                break;
            }
        }

        if ($roamingCourtSelected && !isset($options[self::ROAMING_COURT_VALUE])) {
            $options = [self::ROAMING_COURT_VALUE => self::roamingCourtLabel()] + $options;
        }

        return [
            'options' => $options,
            'available' => true,
            'hasScheduledSessions' => $hasScheduledSessions,
            'optionDates' => $optionDates,
            'gatheringStartDate' => $gatheringStartDate,
            'suggestedBestowedDate' => $this->suggestedBestowedDateFromContext(
                $selectedActivityId,
                $optionDates,
                $gatheringStartDate,
            ),
        ];
    }

    /**
     * Selected value for the court session dropdown.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return string|int|null
     */
    public function courtSessionSelectValue(Bestowal $bestowal): int|string|null
    {
        if ($this->isRoamingCourt($bestowal)) {
            return self::ROAMING_COURT_VALUE;
        }

        return $bestowal->gathering_scheduled_activity_id;
    }

    /**
     * Apply court session form input to a bestowal entity.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param mixed $rawActivityId Submitted gathering_scheduled_activity_id value.
     * @return void
     */
    public function applyCourtSessionSelection(Bestowal $bestowal, mixed $rawActivityId): void
    {
        if ($rawActivityId === null || $rawActivityId === '') {
            $bestowal->roaming_court = false;
            $bestowal->gathering_scheduled_activity_id = null;

            return;
        }

        if ((string)$rawActivityId === self::ROAMING_COURT_VALUE) {
            $bestowal->roaming_court = true;
            $bestowal->gathering_scheduled_activity_id = null;

            return;
        }

        $bestowal->roaming_court = false;
        $bestowal->gathering_scheduled_activity_id = (int)$rawActivityId;
    }

    /**
     * Human-readable label for roaming court.
     */
    public static function roamingCourtLabel(): string
    {
        return __('Roaming Court');
    }

    /**
     * Whether the bestowal is scheduled for roaming court (handles DB boolean types).
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return bool
     */
    public function isRoamingCourt(Bestowal $bestowal): bool
    {
        if (!$bestowal->has('roaming_court')) {
            return false;
        }

        $value = $bestowal->get('roaming_court');
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['t', 'true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['f', 'false', '0', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Help text shown when court slot selection is available.
     */
    public static function fieldHelpText(): string
    {
        return 'Optional. Choose Roaming Court when the award may be given at any time during '
            . 'the event, or pick a specific court session from the Event Schedule so Heralds '
            . 'know when and where to call the recipient into court.';
    }

    /**
     * Message when the gathering has no schedule entries yet.
     */
    public static function noScheduleMessage(): string
    {
        return 'This gathering has no court sessions on the Event Schedule yet. You can still '
            . 'choose Roaming Court, or add sessions on the gathering\'s Schedule tab.';
    }

    /**
     * First day of the gathering event (Y-m-d), or null when unknown.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @return string|null
     */
    public function getGatheringStartDateYmd(?int $gatheringId): ?string
    {
        return $this->findGatheringStartDateYmd($gatheringId);
    }

    /**
     * Court session ID (or roaming) => bestowal date (Y-m-d).
     *
     * @param int|null $gatheringId Gathering primary key.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return array<int|string, string>
     */
    public function buildOptionDates(?int $gatheringId, $member = null): array
    {
        return $this->buildInitialFormData($gatheringId, null, $member)['optionDates'];
    }

    /**
     * Suggested bestowed date: court session day when assigned, otherwise first day of the event.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @param string|int|null $scheduledActivityId Scheduled court activity or roaming value.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return string|null Date in Y-m-d form.
     */
    public function resolveBestowedDate(
        ?int $gatheringId,
        int|string|null $scheduledActivityId = null,
        $member = null,
    ): ?string {
        return $this->buildInitialFormData($gatheringId, $scheduledActivityId, $member)['suggestedBestowedDate'];
    }

    /**
     * Display label for grid and detail views.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return string
     */
    public function formatCourtSlotDisplay(Bestowal $bestowal): string
    {
        if ($this->isRoamingCourt($bestowal)) {
            return self::roamingCourtLabel();
        }

        $activity = $bestowal->gathering_scheduled_activity ?? null;
        if ($activity === null) {
            return '';
        }

        $parts = [];
        if (!empty($activity->display_title)) {
            $parts[] = (string)$activity->display_title;
        }
        if ($activity->start_datetime !== null) {
            $parts[] = TimezoneHelper::formatForDisplay($activity->start_datetime, null, 'M j, g:i A');
        }
        if ($activity->end_datetime !== null) {
            $parts[] = TimezoneHelper::formatForDisplay($activity->end_datetime, null, 'g:i A');
        }

        return implode(' – ', $parts);
    }

    /**
     * @param object $activity Scheduled activity entity.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return string
     */
    private function buildActivityLabel(object $activity, $member = null): string
    {
        $label = (string)($activity->display_title ?? 'Activity #' . $activity->id);
        if ($activity->start_datetime !== null) {
            $label .= ' — ' . $this->formatCourtSessionStart($activity->start_datetime, $member);
        }

        return $label;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return array<int, object>
     */
    private function fetchEligibleScheduledActivities(Bestowal $bestowal): array
    {
        $gatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        $awardId = $bestowal->award_id !== null ? (int)$bestowal->award_id : null;
        if ($gatheringId === null || $gatheringId <= 0 || $awardId === null || $awardId <= 0) {
            return [];
        }

        $allowedActivityIds = TableRegistry::getTableLocator()->get('Awards.AwardGatheringActivities')->find()
            ->select(['gathering_activity_id'])
            ->where(['award_id' => $awardId])
            ->all()
            ->extract('gathering_activity_id')
            ->map(fn($id): int => (int)$id)
            ->filter(fn(int $id): bool => $id > 0)
            ->toList();
        if ($allowedActivityIds === []) {
            return [];
        }

        return TableRegistry::getTableLocator()->get('GatheringScheduledActivities')->find()
            ->select(['id', 'gathering_id', 'gathering_activity_id', 'display_title', 'start_datetime', 'end_datetime'])
            ->where([
                'gathering_id' => $gatheringId,
                'gathering_activity_id IN' => array_values(array_unique($allowedActivityIds)),
            ])
            ->orderBy(['start_datetime' => 'ASC', 'id' => 'ASC'])
            ->all()
            ->toList();
    }

    /**
     * @return array<int, object>
     */
    private function fetchScheduledActivities(?int $gatheringId, int|string|null $selectedActivityId = null): array
    {
        if ($gatheringId === null || $gatheringId <= 0) {
            return [];
        }

        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $activities = $activitiesTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->select(['id', 'gathering_id', 'display_title', 'start_datetime', 'end_datetime'])
            ->orderBy(['start_datetime' => 'ASC'])
            ->all()
            ->toList();

        if (
            $selectedActivityId !== null
            && ctype_digit((string)$selectedActivityId)
        ) {
            $selectedActivityId = (int)$selectedActivityId;
            $hasSelected = false;
            foreach ($activities as $activity) {
                if ((int)$activity->id === $selectedActivityId) {
                    $hasSelected = true;
                    break;
                }
            }
            if (!$hasSelected) {
                $selected = $activitiesTable->find()
                    ->where(['id' => $selectedActivityId])
                    ->select(['id', 'gathering_id', 'display_title', 'start_datetime', 'end_datetime'])
                    ->first();
                if ($selected !== null) {
                    $activities[] = $selected;
                }
            }
        }

        return $activities;
    }

    /**
     * First day of a gathering as Y-m-d, or null when missing.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @return string|null
     */
    private function findGatheringStartDateYmd(?int $gatheringId): ?string
    {
        if ($gatheringId === null || $gatheringId <= 0) {
            return null;
        }

        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->find()
            ->select(['id', 'start_date'])
            ->where(['id' => $gatheringId])
            ->first();
        if ($gathering === null || $gathering->start_date === null) {
            return null;
        }

        return $gathering->start_date->i18nFormat('yyyy-MM-dd');
    }

    /**
     * @param array<int|string, string> $optionDates
     */
    private function suggestedBestowedDateFromContext(
        int|string|null $scheduledActivityId,
        array $optionDates,
        ?string $gatheringStartDate,
    ): ?string {
        if ((string)$scheduledActivityId === self::ROAMING_COURT_VALUE) {
            return $gatheringStartDate;
        }

        if (
            $scheduledActivityId !== null
            && ctype_digit((string)$scheduledActivityId)
        ) {
            $activityId = (int)$scheduledActivityId;
            if ($activityId > 0 && isset($optionDates[$activityId])) {
                return $optionDates[$activityId];
            }
        }

        return $gatheringStartDate;
    }

    /**
     * @param \Cake\I18n\DateTime|string $startDatetime UTC stored start time.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return string
     */
    private function formatCourtSessionStart($startDatetime, $member = null): string
    {
        return TimezoneHelper::formatForDisplay(
            $startDatetime,
            $member,
            'M j, Y g:i A',
        );
    }

    /**
     * @param \Cake\I18n\DateTime|string $startDatetime UTC stored start time.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return string Date in Y-m-d form in the viewer's timezone.
     */
    private function courtSessionDateYmd($startDatetime, $member = null): string
    {
        $local = TimezoneHelper::toUserTimezone($startDatetime, $member);
        if ($local === null) {
            return '';
        }

        return $local->format('Y-m-d');
    }
}
