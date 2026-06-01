<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

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
     * Map database field targets to Stimulus controller target property names.
     *
     * @var array<string, array{block: string, input: string}>
     */
    private const FORM_FIELD_MAP = [
        'gathering_id' => [
            'block' => 'planToGiveBlockTarget',
            'input' => 'planToGiveGatheringTarget',
        ],
        'gathering_scheduled_activity_id' => [
            'block' => 'courtSlotBlockTarget',
            'input' => 'courtSlotTarget',
        ],
        'bestowed_at' => [
            'block' => 'givenBlockTarget',
            'input' => 'givenDateTarget',
        ],
        'close_reason' => [
            'block' => 'closeReasonBlockTarget',
            'input' => 'closeReasonTarget',
        ],
    ];

    /**
     * Build grouped status/state options for dropdowns.
     *
     * @return array<string, array<string, string>>
     */
    public function buildStatusList(): array
    {
        $statusList = Bestowal::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }

        return $statusList;
    }

    /**
     * Build state options allowed for editing a bestowal (current + valid transitions).
     *
     * @param string $currentState Current bestowal state.
     * @return array<string, array<string, string>> Grouped status => [state => state]
     */
    public function buildStateListForEdit(string $currentState): array
    {
        $allowedStates = array_values(array_unique(array_merge(
            [$currentState],
            Bestowal::getValidTransitionsFrom($currentState),
        )));

        $statusList = Bestowal::getStatuses();
        $options = [];
        foreach ($statusList as $statusName => $states) {
            $filtered = array_intersect($states, $allowedStates);
            if ($filtered === []) {
                continue;
            }
            $options[$statusName] = [];
            foreach ($filtered as $state) {
                $options[$statusName][$state] = $state;
            }
        }

        if ($options === []) {
            $options['Current'] = [$currentState => $currentState];
        }

        return $options;
    }

    /**
     * Convert database state rules to Stimulus-friendly target names.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public function buildFormRules(): array
    {
        $rawRules = Bestowal::getStateRules();
        $rules = [];

        foreach ($rawRules as $stateName => $stateRules) {
            $mapped = [];
            foreach ($stateRules as $ruleType => $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                foreach ($fields as $field) {
                    $mapping = self::FORM_FIELD_MAP[(string)$field] ?? null;
                    if ($mapping === null) {
                        continue;
                    }
                    if ($ruleType === 'Required') {
                        $mapped['Visible'][] = $mapping['block'];
                        $mapped['Required'][] = $mapping['input'];
                    } elseif ($ruleType === 'Optional' || $ruleType === 'Visible') {
                        $mapped['Visible'][] = $mapping['block'];
                    } elseif ($ruleType === 'Disabled') {
                        $mapped['Disabled'][] = $mapping['input'];
                    }
                }
            }
            if ($mapped !== []) {
                foreach ($mapped as $type => $targets) {
                    $mapped[$type] = array_values(array_unique($targets));
                }
                $rules[$stateName] = $mapped;
            }
        }

        return $rules;
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
        $currentState = (string)$bestowal->state;
        $statusList = $this->buildStateListForEdit($currentState);
        $rules = $this->buildFormRules();
        $futureOnly = $currentState !== 'Given';
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
        $courtSlotList = $this->courtSlotService->buildOptions(
            $gatheringId,
            $bestowal->gathering_scheduled_activity_id !== null
                ? (int)$bestowal->gathering_scheduled_activity_id
                : null,
            $member,
            !empty($bestowal->roaming_court),
        );
        $courtSlotsAvailable = $gatheringId !== null
            && $this->courtSlotService->gatheringSupportsCourtSlots($gatheringId);
        $courtSlotHasScheduledSessions = $gatheringId !== null
            && $this->courtSlotService->countScheduledActivities($gatheringId) > 0;
        $courtSlotHelpText = BestowalCourtSlotService::fieldHelpText();
        $courtSlotNoScheduleText = BestowalCourtSlotService::noScheduleMessage();
        $courtSlotValue = $this->courtSlotService->courtSessionSelectValue($bestowal);
        $gatheringStartDateYmd = $this->courtSlotService->getGatheringStartDateYmd($gatheringId);
        $courtSessionDates = $this->courtSlotService->buildOptionDates($gatheringId, $member);
        $suggestedBestowedDate = $bestowal->bestowed_at === null
            ? $this->courtSlotService->resolveBestowedDate($gatheringId, $courtSlotValue, $member)
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
     * Prepare view variables for the bulk edit form.
     *
     * @return array<string, mixed>
     */
    public function prepareBulkEditFormData(): array
    {
        $statusList = $this->buildStatusList();
        $rules = $this->buildFormRules();
        $gatheringList = [];

        return compact('rules', 'statusList', 'gatheringList');
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
            'Recommendations.member_id' => (int)$bestowal->member_id,
            'Recommendations.recommendation_group_id IS' => null,
            'Recommendations.state NOT IN' => ['Closed', 'Given'],
        ];
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
     * @return array<string, string> State name => status name
     */
    public function buildStatusMap(): array
    {
        $map = [];
        foreach (Bestowal::getStatuses() as $statusName => $states) {
            foreach ($states as $stateName) {
                $map[$stateName] = $statusName;
            }
        }

        return $map;
    }

}
