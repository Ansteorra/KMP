<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\TimezoneHelper;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\TableRegistry;
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
     * Selected value for the court session dropdown.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @return int|string|null
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

        return filter_var($bestowal->roaming_court, FILTER_VALIDATE_BOOLEAN);
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
        if ($gatheringId === null || $gatheringId <= 0) {
            return null;
        }

        try {
            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gathering = $gatheringsTable->get($gatheringId, fields: ['id', 'start_date']);
            if ($gathering->start_date === null) {
                return null;
            }

            return $gathering->start_date->i18nFormat('yyyy-MM-dd');
        } catch (Throwable) {
            return null;
        }
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
        if (!$this->gatheringSupportsCourtSlots($gatheringId)) {
            return [];
        }

        $dates = [
            self::ROAMING_COURT_VALUE => $this->getGatheringStartDateYmd($gatheringId) ?? '',
        ];

        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
        $activities = $activitiesTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->orderBy(['start_datetime' => 'ASC'])
            ->all();

        foreach ($activities as $activity) {
            if ($activity->start_datetime === null) {
                continue;
            }
            $ymd = $this->courtSessionDateYmd($activity->start_datetime, $member);
            if ($ymd !== '') {
                $dates[(int)$activity->id] = $ymd;
            }
        }

        return $dates;
    }

    /**
     * Suggested bestowed date: court session day when assigned, otherwise first day of the event.
     *
     * @param int|null $gatheringId Gathering primary key.
     * @param int|string|null $scheduledActivityId Scheduled court activity or roaming value.
     * @param \App\Model\Entity\Member|array|null $member Viewer for timezone conversion.
     * @return string|null Date in Y-m-d form.
     */
    public function resolveBestowedDate(
        ?int $gatheringId,
        int|string|null $scheduledActivityId = null,
        $member = null,
    ): ?string {
        if ($gatheringId !== null && $gatheringId > 0) {
            if ((string)$scheduledActivityId === self::ROAMING_COURT_VALUE) {
                return $this->getGatheringStartDateYmd($gatheringId);
            }

            if (
                is_int($scheduledActivityId)
                || (is_string($scheduledActivityId) && ctype_digit($scheduledActivityId))
            ) {
                $activityId = (int)$scheduledActivityId;
                if ($activityId > 0) {
                    $optionDates = $this->buildOptionDates($gatheringId, $member);
                    if (isset($optionDates[$activityId])) {
                        return $optionDates[$activityId];
                    }

                    try {
                        $activitiesTable = TableRegistry::getTableLocator()->get('GatheringScheduledActivities');
                        $activity = $activitiesTable->get($activityId);
                        if ($activity->start_datetime !== null) {
                            return $this->courtSessionDateYmd($activity->start_datetime, $member);
                        }
                    } catch (Throwable) {
                    }
                }
            }
        }

        return $this->getGatheringStartDateYmd($gatheringId);
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
