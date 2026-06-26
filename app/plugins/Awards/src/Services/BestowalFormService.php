<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * Prepares lookup data for bestowal edit and bulk edit forms.
 */
class BestowalFormService
{
    private BestowalGatheringLookupService $gatheringLookupService;

    private BestowalCourtSlotService $courtSlotService;

    /**
     * @param \Awards\Services\BestowalGatheringLookupService|null $gatheringLookupService Gathering lookup service.
     * @param \Awards\Services\BestowalCourtSlotService|null $courtSlotService Court slot helper.
     */
    public function __construct(
        ?BestowalGatheringLookupService $gatheringLookupService = null,
        ?BestowalCourtSlotService $courtSlotService = null,
    ) {
        $this->gatheringLookupService = $gatheringLookupService ?? new BestowalGatheringLookupService();
        $this->courtSlotService = $courtSlotService ?? new BestowalCourtSlotService();
    }

    /**
     * Lifecycle bestowals have no status/state dropdown options.
     *
     * @return array<string, array<string, string>>
     */
    public function buildStatusList(): array
    {
        return [];
    }

    /**
     * Lifecycle bestowals expose no field-rule mapping.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public function buildFormRules(): array
    {
        return [];
    }

    /**
     * Prepare view variables for a single bestowal edit form.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table.
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal entity.
     * @param \App\Model\Entity\Member|array|null $member Current user for timezone-aware labels.
     * @return array<string, mixed>
     */
    public function prepareEditFormData(Table $bestowalsTable, Bestowal $bestowal, $member = null): array
    {
        $futureOnly = (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN)
            !== Bestowal::LIFECYCLE_GIVEN;
        $statusList = [];
        $rules = [];
        $gatheringData = $this->gatheringLookupService->getFilteredGatheringsForBestowal(
            $bestowal,
            $futureOnly,
            $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null,
        );
        $gatheringList = $gatheringData['gatherings'];
        $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'];
        $assignedGatheringCancelled = $bestowal->gathering_id !== null
            && in_array((int)$bestowal->gathering_id, $cancelledGatheringIds, true);
        $memberAttendanceGatherings = $bestowal->member_id !== null
            ? $this->gatheringLookupService->getMemberAttendanceGatherings((int)$bestowal->member_id)
            : [];
        $gatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        $courtSlotValue = $this->courtSlotService->courtSessionSelectValue($bestowal);
        $courtSlotData = $this->courtSlotService->buildInitialFormData(
            $gatheringId,
            $courtSlotValue,
            $member,
            !empty($bestowal->roaming_court),
        );
        $courtSlotList = $courtSlotData['options'];
        $courtSlotsAvailable = $courtSlotData['available'];
        $courtSlotHasScheduledSessions = $courtSlotData['hasScheduledSessions'];
        $courtSlotHelpText = BestowalCourtSlotService::fieldHelpText();
        $courtSlotNoScheduleText = BestowalCourtSlotService::noScheduleMessage();
        $gatheringStartDateYmd = $courtSlotData['gatheringStartDate'];
        $courtSessionDates = $courtSlotData['optionDates'];
        $suggestedBestowedDate = $bestowal->bestowed_at === null
            ? $courtSlotData['suggestedBestowedDate']
            : null;
        $gatheringScheduleUrl = null;
        if ($bestowal->hasValue('gathering') && !empty($bestowal->gathering->public_id)) {
            $gatheringScheduleUrl = [
                'plugin' => null,
                'controller' => 'Gatherings',
                'action' => 'view',
                $bestowal->gathering->public_id,
                '#' => 'nav-schedule',
            ];
        }
        $linkableRecommendations = $this->findLinkableRecommendations(
            $bestowalsTable,
            $bestowal,
        );
        $statusMap = $this->buildStatusMap();
        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $awardsDomains = $awardsTable->Domains->find('list', limit: 200)->all();
        $domainId = null;
        $currentAwardId = $bestowal->award_id;
        if ($bestowal->hasValue('award')) {
            $domainId = $bestowal->award->domain_id;
        } elseif (
            $bestowal->hasValue('primary_recommendation')
            && $bestowal->primary_recommendation->hasValue('award')
        ) {
            $domainId = $bestowal->primary_recommendation->award->domain_id;
            $currentAwardId ??= $bestowal->primary_recommendation->award_id;
        }
        $awards = [];
        if ($domainId !== null) {
            $awards = $awardsTable->find('selectable', [
                'domain_id' => $domainId,
                'current_award_id' => $currentAwardId,
            ])
                ->select(['id', 'name', 'specialties'])
                ->limit(200)
                ->all();
        }

        return compact(
            'rules',
            'bestowal',
            'statusList',
            'gatheringList',
            'courtSlotList',
            'courtSlotValue',
            'courtSlotsAvailable',
            'courtSlotHasScheduledSessions',
            'courtSlotHelpText',
            'courtSlotNoScheduleText',
            'gatheringScheduleUrl',
            'linkableRecommendations',
            'statusMap',
            'cancelledGatheringIds',
            'assignedGatheringCancelled',
            'memberAttendanceGatherings',
            'awardsDomains',
            'awards',
            'domainId',
            'gatheringStartDateYmd',
            'courtSessionDates',
            'suggestedBestowedDate',
        );
    }

    /**
     * Prepare view variables for the ad-hoc bestowal creation form.
     *
     * @param \App\Model\Entity\Member|array|null $member Current user for timezone-aware labels.
     * @return array<string, mixed>
     */
    public function prepareAdHocFormData($member = null): array
    {
        $bestowal = new Bestowal([
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]);
        $statusList = $this->buildStatusList();
        $rules = $this->buildFormRules();
        $statusMap = $this->buildStatusMap();
        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $awardsDomains = $awardsTable->Domains->find('list', limit: 200)->all();
        $awards = [];
        $gatheringList = [];
        $courtSlotList = [];
        $courtSlotValue = null;
        $courtSlotsAvailable = false;
        $courtSlotHasScheduledSessions = false;
        $courtSlotHelpText = BestowalCourtSlotService::fieldHelpText();
        $courtSlotNoScheduleText = BestowalCourtSlotService::noScheduleMessage();
        $gatheringScheduleUrl = null;
        $cancelledGatheringIds = [];
        $assignedGatheringCancelled = false;
        $memberAttendanceGatherings = [];
        $domainId = null;
        $gatheringStartDateYmd = null;
        $courtSessionDates = [];
        $suggestedBestowedDate = null;
        $linkableRecommendations = [];

        return compact(
            'rules',
            'bestowal',
            'statusList',
            'gatheringList',
            'courtSlotList',
            'courtSlotValue',
            'courtSlotsAvailable',
            'courtSlotHasScheduledSessions',
            'courtSlotHelpText',
            'courtSlotNoScheduleText',
            'gatheringScheduleUrl',
            'linkableRecommendations',
            'statusMap',
            'cancelledGatheringIds',
            'assignedGatheringCancelled',
            'memberAttendanceGatherings',
            'awardsDomains',
            'awards',
            'domainId',
            'gatheringStartDateYmd',
            'courtSessionDates',
            'suggestedBestowedDate',
        );
    }

    /**
     * Find recommendations that may be linked to the bestowal.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table.
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal.
     * @return array<int, string> Recommendation ID => label
     */
    public function findLinkableRecommendations(Table $bestowalsTable, Bestowal $bestowal): array
    {
        $recommendationsTable = $bestowalsTable->getAssociation('Recommendations')->getTarget();
        $linkedIds = [];
        foreach ($bestowal->recommendations ?? [] as $linked) {
            $linkedIds[] = (int)$linked->id;
        }

        $conditions = [
            'Recommendations.recommendation_group_id IS' => null,
            'Recommendations.state NOT IN' => ['Closed', 'Given'],
        ];
        if ($bestowal->member_id !== null) {
            $conditions['Recommendations.member_id'] = (int)$bestowal->member_id;
        } else {
            $conditions['Recommendations.member_id IS'] = null;
            $conditions['Recommendations.member_sca_name'] = (string)$bestowal->member_sca_name;
        }
        if ($linkedIds !== []) {
            $conditions['OR'] = [
                'Recommendations.bestowal_id IS' => null,
                'Recommendations.id IN' => $linkedIds,
            ];
        } else {
            $conditions['Recommendations.bestowal_id IS'] = null;
        }

        $query = $recommendationsTable->find()
            ->contain(['Awards' => ['Levels']])
            ->where($conditions)
            ->orderBy(['Recommendations.created' => 'DESC']);

        $options = [];
        foreach ($query->all() as $recommendation) {
            if (
                $recommendation->bestowal_id !== null
                && (int)$recommendation->bestowal_id !== (int)$bestowal->id
            ) {
                try {
                    $otherBestowal = $bestowalsTable->get((int)$recommendation->bestowal_id);
                    if ($otherBestowal->isActiveBestowal()) {
                        continue;
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            $award = $recommendation->award ?? null;
            $label = trim((string)($award->abbreviation ?? $award->name ?? 'Rec #' . $recommendation->id));
            $level = $award->level ?? null;
            if ($level !== null && !empty($level->name)) {
                $label .= ' (' . (string)$level->name . ')';
            }
            $label .= ' — ' . (string)$recommendation->state;
            $options[(int)$recommendation->id] = $label;
        }

        return $options;
    }

    /**
     * Lifecycle bestowals expose no state-to-status mapping.
     *
     * @return array<string, string>
     */
    public function buildStatusMap(): array
    {
        return [];
    }
}
