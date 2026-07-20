<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * Resolves gathering options for bestowal edit forms with member attendance markers.
 */
class BestowalGatheringLookupService
{
    use LocatorAwareTrait;

    /**
     * Build gathering options for a single bestowal edit form.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal with recommendations contain.
     * @param bool $futureOnly When true, only include future gatherings.
     * @param int|null $selectedGatheringId Currently selected gathering to always include.
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>, rankedGatherings: array<int, array<string, mixed>>}
     */
    public function getFilteredGatheringsForBestowal(
        Bestowal $bestowal,
        bool $futureOnly = true,
        ?int $selectedGatheringId = null,
        ?int $awardIdOverride = null,
    ): array {
        $awardIds = $awardIdOverride !== null
            ? [$awardIdOverride]
            : $this->collectAwardIdsFromBestowal($bestowal);
        $memberId = $bestowal->member_id !== null ? (int)$bestowal->member_id : null;
        $recommendationIds = $this->collectRecommendationIdsFromBestowal($bestowal);
        $includeGatheringIds = array_values(array_filter([
            $selectedGatheringId,
            $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null,
        ]));

        return $this->buildGatheringOptions(
            $awardIds,
            $memberId !== null ? [$memberId] : [],
            $futureOnly,
            $includeGatheringIds,
            false,
            $recommendationIds,
        );
    }

    /**
     * Build gathering options for bulk bestowal edit across multiple bestowals.
     *
     * @param array<int> $bestowalIds Selected bestowal IDs.
     * @param bool $futureOnly When true, only include future gatherings.
     * @param int|null $selectedGatheringId Currently selected gathering to always include.
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>, rankedGatherings: array<int, array<string, mixed>>}
     */
    public function getFilteredGatheringsForBestowalIds(
        array $bestowalIds,
        bool $futureOnly = true,
        ?int $selectedGatheringId = null,
    ): array {
        $bestowalIds = array_values(array_unique(array_filter(array_map('intval', $bestowalIds))));
        if ($bestowalIds === []) {
            return ['gatherings' => [], 'cancelledGatheringIds' => [], 'rankedGatherings' => []];
        }

        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowals = $bestowalsTable->find()
            ->where(['Bestowals.id IN' => $bestowalIds])
            ->contain([
                'Recommendations' => function ($query) {
                    return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                },
            ])
            ->select(['Bestowals.id', 'Bestowals.award_id', 'Bestowals.member_id'])
            ->all();

        $awardIds = [];
        $memberIds = [];
        $recommendationIds = [];
        foreach ($bestowals as $bestowal) {
            foreach ($this->collectAwardIdsFromBestowal($bestowal) as $awardId) {
                $awardIds[] = $awardId;
            }
            foreach ($this->collectRecommendationIdsFromBestowal($bestowal) as $recommendationId) {
                $recommendationIds[] = $recommendationId;
            }
            if ($bestowal->member_id !== null) {
                $memberIds[] = (int)$bestowal->member_id;
            }
        }

        $includeGatheringIds = $selectedGatheringId !== null ? [$selectedGatheringId] : [];

        return $this->buildGatheringOptions(
            array_values(array_unique($awardIds)),
            array_values(array_unique($memberIds)),
            $futureOnly,
            $includeGatheringIds,
            true,
            array_values(array_unique($recommendationIds)),
        );
    }

    /**
     * Validate a submitted gathering without treating the submitted value as a sticky option.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal with recommendations contain.
     * @param int $gatheringId Gathering ID to validate.
     * @param bool $futureOnly When true, only future gatherings are selectable.
     * @return bool
     */
    public function isGatheringSelectableForBestowal(
        Bestowal $bestowal,
        int $gatheringId,
        bool $futureOnly = true,
    ): bool {
        if ($gatheringId <= 0) {
            return false;
        }

        $awardIds = $this->collectAwardIdsFromBestowal($bestowal);
        $memberId = $bestowal->member_id !== null ? (int)$bestowal->member_id : null;
        $result = $this->buildGatheringOptions(
            $awardIds,
            $memberId !== null ? [$memberId] : [],
            $futureOnly,
            [],
            false,
        );

        return array_key_exists($gatheringId, $result['gatherings']);
    }

    /**
     * Gatherings the member marked as likely attendance shared with crown/kingdom.
     *
     * @param int $memberId Member ID.
     * @return array<int, \App\Model\Entity\Gathering>
     */
    public function getMemberAttendanceGatherings(int $memberId): array
    {
        if ($memberId <= 0) {
            return [];
        }

        $attendances = $this->fetchTable('GatheringAttendances')->find()
            ->contain([
                'Gatherings' => function ($query) {
                    return $query
                        ->select(['id', 'name', 'start_date', 'end_date'])
                        ->contain(['Branches' => function ($q) {
                            return $q->select(['id', 'name']);
                        }]);
                },
            ])
            ->where([
                'GatheringAttendances.member_id' => $memberId,
                'GatheringAttendances.deleted IS' => null,
                'OR' => [
                    'GatheringAttendances.share_with_crown' => true,
                    'GatheringAttendances.share_with_kingdom' => true,
                ],
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC'])
            ->all();

        $gatherings = [];
        foreach ($attendances as $attendance) {
            if ($attendance->gathering === null) {
                continue;
            }
            $gatherings[(int)$attendance->gathering->id] = $attendance->gathering;
        }

        return array_values($gatherings);
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal.
     * @return array<int>
     */
    private function collectAwardIdsFromBestowal(Bestowal $bestowal): array
    {
        if ($bestowal->award_id !== null) {
            return [(int)$bestowal->award_id];
        }

        $awardIds = [];
        foreach ($bestowal->recommendations ?? [] as $recommendation) {
            if ($recommendation->award_id !== null) {
                $awardIds[] = (int)$recommendation->award_id;
            }
        }

        return array_values(array_unique($awardIds));
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Loaded bestowal.
     * @return array<int>
     */
    private function collectRecommendationIdsFromBestowal(Bestowal $bestowal): array
    {
        $recommendationIds = [];
        foreach ($bestowal->recommendations ?? [] as $recommendation) {
            if ($recommendation->id !== null) {
                $recommendationIds[] = (int)$recommendation->id;
            }
        }

        return array_values(array_unique($recommendationIds));
    }

    /**
     * @param array<int> $awardIds Award IDs linked through bestowal recommendations.
     * @param array<int> $memberIds Member IDs for attendance markers.
     * @param bool $futureOnly Limit to future gatherings when true.
     * @param array<int> $includeGatheringIds Gatherings that must appear even if filtered out.
     * @param bool $bulkMode When true, attendance counts use *(N) suffix for multiple members.
     * @param array<int> $recommendationIds Recommendation IDs with suggested gatherings.
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>, rankedGatherings: array<int, array<string, mixed>>}
     */
    private function buildGatheringOptions(
        array $awardIds,
        array $memberIds,
        bool $futureOnly,
        array $includeGatheringIds,
        bool $bulkMode,
        array $recommendationIds = [],
    ): array {
        $includeGatheringIds = array_values(array_unique(array_filter(array_map('intval', $includeGatheringIds))));

        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringsData = [];

        if ($awardIds === []) {
            $gatheringsData = $this->fetchGatheringsQuery($gatheringsTable, [], $futureOnly)
                ->all()
                ->toList();
        } elseif ($bulkMode) {
            $supportedGatheringIds = $this->resolveGatheringIdsSupportingAllAwards($awardIds);
            if ($supportedGatheringIds !== []) {
                $gatheringsData = $this->fetchGatheringsQuery(
                    $gatheringsTable,
                    [],
                    $futureOnly,
                    $supportedGatheringIds,
                )
                    ->all()
                    ->toList();
            }
        } else {
            $commonActivityIds = $this->resolveCommonActivityIds($awardIds);
            if ($commonActivityIds !== []) {
                $gatheringsData = $this->fetchGatheringsQuery($gatheringsTable, $commonActivityIds, $futureOnly)
                    ->all()
                    ->toList();
            }
        }

        $attendanceMap = $this->buildAttendanceMap($memberIds, $bulkMode);
        $suggestedMap = $this->buildSuggestedGatheringMap($recommendationIds, $bulkMode);
        $gatherings = [];
        $cancelledGatheringIds = [];
        $rankItems = [];

        foreach ($gatheringsData as $gathering) {
            $rankItems[(int)$gathering->id] = $this->buildRankedGatheringItem(
                $gathering,
                $attendanceMap,
                $suggestedMap,
                $bulkMode,
            );
            $label = $rankItems[(int)$gathering->id]['label'];
            if ($gathering->cancelled_at !== null) {
                $label = '[CANCELLED] ' . $label;
                $cancelledGatheringIds[] = (int)$gathering->id;
            }
            $gatherings[(int)$gathering->id] = $label;
        }

        if ($includeGatheringIds !== []) {
            $missingIds = array_values(array_diff($includeGatheringIds, array_keys($gatherings)));
            if ($missingIds !== []) {
                $included = $gatheringsTable->find()
                    ->contain(['Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }])
                    ->where(['Gatherings.id IN' => $missingIds])
                    ->all();

                $prepended = [];
                foreach ($includeGatheringIds as $includedId) {
                    foreach ($included as $gathering) {
                        if ((int)$gathering->id !== $includedId) {
                            continue;
                        }
                        $rankItems[(int)$gathering->id] = $this->buildRankedGatheringItem(
                            $gathering,
                            $attendanceMap,
                            $suggestedMap,
                            $bulkMode,
                        );
                        $label = $rankItems[(int)$gathering->id]['label'];
                        if ($gathering->cancelled_at !== null) {
                            $label = '[CANCELLED] ' . $label;
                        }
                        $prepended[(int)$gathering->id] = $label;
                    }
                }
                $gatherings = $prepended + $gatherings;
            }
            $cancelledGatheringIds = array_values(array_diff($cancelledGatheringIds, $includeGatheringIds));
        }

        $rankedGatherings = $this->groupRankedGatherings($rankItems);
        $gatherings = [];
        foreach ($rankedGatherings as $group) {
            foreach ($group['items'] as $item) {
                $gatherings[(int)$item['id']] = (string)$item['label'];
            }
        }

        return [
            'gatherings' => $gatherings,
            'cancelledGatheringIds' => $cancelledGatheringIds,
            'rankedGatherings' => $rankedGatherings,
        ];
    }

    /**
     * @param array<int> $awardIds Award IDs.
     * @return array<int>
     */
    private function resolveGatheringIdsSupportingAllAwards(array $awardIds): array
    {
        $awardIds = array_values(array_unique(array_filter(array_map('intval', $awardIds))));
        if ($awardIds === []) {
            return [];
        }

        $rows = $this->fetchTable('Awards.AwardGatheringActivities')->find()
            ->select([
                'award_id' => 'AwardGatheringActivities.award_id',
                'gathering_id' => 'GatheringsGatheringActivities.gathering_id',
            ])
            ->innerJoin(
                ['GatheringsGatheringActivities' => 'gatherings_gathering_activities'],
                [
                    'GatheringsGatheringActivities.gathering_activity_id = ' .
                        'AwardGatheringActivities.gathering_activity_id',
                ],
            )
            ->where(['AwardGatheringActivities.award_id IN' => $awardIds])
            ->enableHydration(false)
            ->all();

        $awardIdsByGathering = [];
        foreach ($rows as $row) {
            $gatheringId = (int)$row['gathering_id'];
            $awardIdsByGathering[$gatheringId][(int)$row['award_id']] = true;
        }

        $requiredCount = count($awardIds);
        $gatheringIds = [];
        foreach ($awardIdsByGathering as $gatheringId => $supportedAwardIds) {
            if (count($supportedAwardIds) === $requiredCount) {
                $gatheringIds[] = (int)$gatheringId;
            }
        }

        return $gatheringIds;
    }

    /**
     * @param array<int> $awardIds Award IDs.
     * @return array<int>
     */
    private function resolveCommonActivityIds(array $awardIds): array
    {
        if ($awardIds === []) {
            return [];
        }

        $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
        $commonActivityIds = null;
        foreach ($awardIds as $awardId) {
            $linkedActivities = $awardGatheringActivitiesTable->find()
                ->where(['award_id' => $awardId])
                ->select(['gathering_activity_id'])
                ->all();
            $activityIds = [];
            foreach ($linkedActivities as $row) {
                $activityIds[] = (int)$row->gathering_activity_id;
            }

            if ($commonActivityIds === null) {
                $commonActivityIds = $activityIds;
            } else {
                $commonActivityIds = array_values(array_intersect($commonActivityIds, $activityIds));
            }
        }

        return array_values($commonActivityIds ?? []);
    }

    /**
     * @param array<int> $memberIds Member IDs.
     * @param bool $bulkMode Use attendance counts when true.
     * @return array<int, bool|int> gathering_id => bool (single) or count (bulk)
     */
    private function buildAttendanceMap(array $memberIds, bool $bulkMode): array
    {
        if ($memberIds === []) {
            return [];
        }

        $attendanceTable = $this->fetchTable('GatheringAttendances');
        $conditions = [
            'member_id IN' => $memberIds,
            'deleted IS' => null,
        ];
        if ($bulkMode) {
            $conditions['OR'] = [
                'share_with_crown' => true,
                'share_with_kingdom' => true,
            ];
        }

        $attendances = $attendanceTable->find()
            ->where($conditions)
            ->select(['gathering_id', 'member_id', 'share_with_crown', 'share_with_kingdom'])
            ->all();

        $attendanceMap = [];
        $countedMembers = [];
        foreach ($attendances as $attendance) {
            $gatheringId = (int)$attendance->gathering_id;
            if ($bulkMode) {
                $memberId = (int)$attendance->member_id;
                if (isset($countedMembers[$gatheringId][$memberId])) {
                    continue;
                }
                $countedMembers[$gatheringId][$memberId] = true;
                $attendanceMap[$gatheringId] = ($attendanceMap[$gatheringId] ?? 0) + 1;
                continue;
            }

            $attendanceMap[$gatheringId] = (bool)$attendance->share_with_crown
                || (bool)$attendance->share_with_kingdom;
        }

        return $attendanceMap;
    }

    /**
     * @param \Cake\ORM\Table $gatheringsTable Gatherings table.
     * @param array<int> $activityIds Gathering activity IDs to filter by.
     * @param bool $futureOnly Limit to future gatherings when true.
     * @param array<int> $gatheringIds Gathering IDs to filter by.
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function fetchGatheringsQuery(
        $gatheringsTable,
        array $activityIds,
        bool $futureOnly,
        array $gatheringIds = [],
    ): SelectQuery {
        $query = $gatheringsTable->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->select([
                'Gatherings.id',
                'Gatherings.name',
                'Gatherings.start_date',
                'Gatherings.end_date',
                'Gatherings.branch_id',
                'Gatherings.cancelled_at',
            ]);

        if ($futureOnly) {
            $query->where(['Gatherings.start_date >' => DateTime::now()])
                ->orderBy(['Gatherings.start_date' => 'ASC']);
        } else {
            $query->orderBy(['Gatherings.start_date' => 'ASC']);
        }

        if ($activityIds !== []) {
            $query->matching('GatheringActivities', function ($q) use ($activityIds) {
                return $q->where(['GatheringActivities.id IN' => $activityIds]);
            });
        }
        if ($gatheringIds !== []) {
            $query->where(['Gatherings.id IN' => array_values(array_unique(array_map('intval', $gatheringIds)))]);
        }

        return $query;
    }

    /**
     * @param \App\Model\Entity\Gathering $gathering Gathering entity with branch contain.
     * @param array<int, bool|int> $attendanceMap Attendance markers keyed by gathering ID.
     * @param array<int, int> $suggestedMap Suggested event counts keyed by gathering ID.
     * @param bool $bulkMode Use count suffix when true.
     * @return string
     */
    private function formatGatheringLabel(
        $gathering,
        array $attendanceMap,
        array $suggestedMap,
        bool $bulkMode,
    ): string {
        $branchName = $gathering->branch->name ?? '';
        $label = $gathering->name . ' in ' . $branchName . ' on '
            . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

        $gatheringId = (int)$gathering->id;
        if ($bulkMode && isset($attendanceMap[$gatheringId]) && (int)$attendanceMap[$gatheringId] > 0) {
            $label .= ' RSVP ' . (int)$attendanceMap[$gatheringId];
        } elseif (
            !$bulkMode
            && isset($attendanceMap[$gatheringId])
            && $attendanceMap[$gatheringId]
        ) {
            $label .= ' RSVP';
        } elseif (isset($suggestedMap[$gatheringId]) && (int)$suggestedMap[$gatheringId] > 0) {
            $label .= $bulkMode ? ' Suggested ' . (int)$suggestedMap[$gatheringId] : ' Suggested';
        }

        return $label;
    }

    /**
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param bool $bulkMode Use counts when true.
     * @return array<int, int>
     */
    private function buildSuggestedGatheringMap(array $recommendationIds, bool $bulkMode): array
    {
        $recommendationIds = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        if ($recommendationIds === []) {
            return [];
        }

        $rows = $this->fetchTable('Awards.Recommendations')->find()
            ->select([
                'Recommendations.id',
                'Recommendations.bestowal_id',
                'suggested_gathering_id' => 'SuggestedGatherings.gathering_id',
            ])
            ->innerJoin(
                ['SuggestedGatherings' => 'awards_recommendations_events'],
                ['SuggestedGatherings.recommendation_id = Recommendations.id'],
            )
            ->where([
                'Recommendations.id IN' => $recommendationIds,
                'SuggestedGatherings.gathering_id IS NOT' => null,
            ])
            ->enableHydration(false)
            ->all();

        $suggested = [];
        $countedKeys = [];
        foreach ($rows as $row) {
            $gatheringId = (int)$row['suggested_gathering_id'];
            if ($gatheringId <= 0) {
                continue;
            }
            if ($bulkMode) {
                $countKey = !empty($row['bestowal_id'])
                    ? 'bestowal:' . (int)$row['bestowal_id']
                    : 'recommendation:' . (int)$row['id'];
                if (isset($countedKeys[$gatheringId][$countKey])) {
                    continue;
                }
                $countedKeys[$gatheringId][$countKey] = true;
                $suggested[$gatheringId] = ($suggested[$gatheringId] ?? 0) + 1;
            } else {
                $suggested[$gatheringId] = 1;
            }
        }

        return $suggested;
    }

    /**
     * @param \App\Model\Entity\Gathering $gathering Gathering entity with branch contain.
     * @param array<int, bool|int> $attendanceMap Attendance markers keyed by gathering ID.
     * @param array<int, int> $suggestedMap Suggested event counts keyed by gathering ID.
     * @param bool $bulkMode Use count badges when true.
     * @return array<string, mixed>
     */
    private function buildRankedGatheringItem(
        $gathering,
        array $attendanceMap,
        array $suggestedMap,
        bool $bulkMode,
    ): array {
        $gatheringId = (int)$gathering->id;
        $rsvpCount = (int)($attendanceMap[$gatheringId] ?? 0);
        $suggestedCount = (int)($suggestedMap[$gatheringId] ?? 0);
        $rank = 'other';
        if ($rsvpCount > 0) {
            $rank = 'rsvp';
        } elseif ($suggestedCount > 0) {
            $rank = 'suggested';
        }

        return [
            'id' => $gatheringId,
            'rank' => $rank,
            'label' => $this->formatGatheringLabel($gathering, $attendanceMap, $suggestedMap, $bulkMode),
            'name' => (string)$gathering->name,
            'branch' => (string)($gathering->branch->name ?? ''),
            'startDate' => $gathering->start_date?->toDateString() ?? '',
            'endDate' => $gathering->end_date?->toDateString() ?? '',
            'rsvpCount' => $rsvpCount,
            'suggestedCount' => $suggestedCount,
            'cancelled' => $gathering->cancelled_at !== null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items Ranked items by gathering ID.
     * @return array<int, array<string, mixed>>
     */
    private function groupRankedGatherings(array $items): array
    {
        $groups = [
            'rsvp' => ['key' => 'rsvp', 'label' => __('Best match: recipient RSVP'), 'items' => []],
            'suggested' => ['key' => 'suggested', 'label' => __('Suggested by recommendation'), 'items' => []],
            'other' => ['key' => 'other', 'label' => __('Other eligible gatherings'), 'items' => []],
        ];

        foreach ($items as $item) {
            $groups[$item['rank'] ?? 'other']['items'][] = $item;
        }

        foreach ($groups as &$group) {
            usort($group['items'], static function (array $a, array $b): int {
                return strcmp((string)$a['startDate'], (string)$b['startDate'])
                    ?: strcmp((string)$a['label'], (string)$b['label']);
            });
        }
        unset($group);

        return array_values(array_filter($groups, static fn(array $group): bool => $group['items'] !== []));
    }
}
