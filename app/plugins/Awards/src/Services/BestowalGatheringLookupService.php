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
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>}
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
        );
    }

    /**
     * Build gathering options for bulk bestowal edit across multiple bestowals.
     *
     * @param array<int> $bestowalIds Selected bestowal IDs.
     * @param bool $futureOnly When true, only include future gatherings.
     * @param int|null $selectedGatheringId Currently selected gathering to always include.
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>}
     */
    public function getFilteredGatheringsForBestowalIds(
        array $bestowalIds,
        bool $futureOnly = true,
        ?int $selectedGatheringId = null,
    ): array {
        $bestowalIds = array_values(array_unique(array_filter(array_map('intval', $bestowalIds))));
        if ($bestowalIds === []) {
            return ['gatherings' => [], 'cancelledGatheringIds' => []];
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
        foreach ($bestowals as $bestowal) {
            foreach ($this->collectAwardIdsFromBestowal($bestowal) as $awardId) {
                $awardIds[] = $awardId;
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
     * @param array<int> $awardIds Award IDs linked through bestowal recommendations.
     * @param array<int> $memberIds Member IDs for attendance markers.
     * @param bool $futureOnly Limit to future gatherings when true.
     * @param array<int> $includeGatheringIds Gatherings that must appear even if filtered out.
     * @param bool $bulkMode When true, attendance counts use *(N) suffix for multiple members.
     * @return array{gatherings: array<int, string>, cancelledGatheringIds: array<int, int>}
     */
    private function buildGatheringOptions(
        array $awardIds,
        array $memberIds,
        bool $futureOnly,
        array $includeGatheringIds,
        bool $bulkMode,
    ): array {
        $includeGatheringIds = array_values(array_unique(array_filter(array_map('intval', $includeGatheringIds))));
        $commonActivityIds = $this->resolveCommonActivityIds($awardIds);

        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gatheringsData = [];

        if ($awardIds === []) {
            $gatheringsData = $this->fetchGatheringsQuery($gatheringsTable, [], $futureOnly)
                ->all()
                ->toList();
        } elseif ($commonActivityIds !== []) {
            $gatheringsData = $this->fetchGatheringsQuery($gatheringsTable, $commonActivityIds, $futureOnly)
                ->all()
                ->toList();
        }

        $attendanceMap = $this->buildAttendanceMap($memberIds, $bulkMode);
        $gatherings = [];
        $cancelledGatheringIds = [];

        foreach ($gatheringsData as $gathering) {
            $label = $this->formatGatheringLabel($gathering, $attendanceMap, $bulkMode);
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
                        $label = $this->formatGatheringLabel($gathering, $attendanceMap, $bulkMode);
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

        $gatherings = $this->sortGatheringsByAttendance($gatherings, $attendanceMap, $bulkMode);

        return [
            'gatherings' => $gatherings,
            'cancelledGatheringIds' => $cancelledGatheringIds,
        ];
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
            $conditions['share_with_crown'] = true;
        }

        $attendances = $attendanceTable->find()
            ->where($conditions)
            ->select(['gathering_id', 'member_id', 'share_with_crown'])
            ->all();

        $attendanceMap = [];
        foreach ($attendances as $attendance) {
            $gatheringId = (int)$attendance->gathering_id;
            if ($bulkMode) {
                $attendanceMap[$gatheringId] = ($attendanceMap[$gatheringId] ?? 0) + 1;
                continue;
            }

            $attendanceMap[$gatheringId] = (bool)$attendance->share_with_crown;
        }

        return $attendanceMap;
    }

    /**
     * @param \Cake\ORM\Table $gatheringsTable Gatherings table.
     * @param array<int> $activityIds Gathering activity IDs to filter by.
     * @param bool $futureOnly Limit to future gatherings when true.
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function fetchGatheringsQuery($gatheringsTable, array $activityIds, bool $futureOnly): SelectQuery
    {
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
            $query->orderBy(['Gatherings.start_date' => 'DESC']);
        }

        if ($activityIds !== []) {
            $query->matching('GatheringActivities', function ($q) use ($activityIds) {
                return $q->where(['GatheringActivities.id IN' => $activityIds]);
            });
        }

        return $query;
    }

    /**
     * @param \App\Model\Entity\Gathering $gathering Gathering entity with branch contain.
     * @param array<int, bool|int> $attendanceMap Attendance markers keyed by gathering ID.
     * @param bool $bulkMode Use count suffix when true.
     * @return string
     */
    private function formatGatheringLabel(
        $gathering,
        array $attendanceMap,
        bool $bulkMode,
    ): string {
        $branchName = $gathering->branch->name ?? '';
        $label = $gathering->name . ' in ' . $branchName . ' on '
            . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

        $gatheringId = (int)$gathering->id;
        if ($bulkMode && isset($attendanceMap[$gatheringId]) && (int)$attendanceMap[$gatheringId] > 0) {
            $label .= ' *(' . (int)$attendanceMap[$gatheringId] . ')';
        } elseif (
            !$bulkMode
            && isset($attendanceMap[$gatheringId])
            && $attendanceMap[$gatheringId]
        ) {
            $label .= ' *';
        }

        return $label;
    }

    /**
     * @param array<int, string> $gatherings Gatherings keyed by ID.
     * @param array<int, bool|int> $attendanceMap Attendance markers keyed by gathering ID.
     * @param bool $bulkMode Treat attendance map values as counts when true.
     * @return array<int, string>
     */
    private function sortGatheringsByAttendance(array $gatherings, array $attendanceMap, bool $bulkMode): array
    {
        $attended = [];
        $other = [];
        foreach ($gatherings as $id => $label) {
            $hasAttendance = $bulkMode
                ? isset($attendanceMap[$id]) && (int)$attendanceMap[$id] > 0
                : isset($attendanceMap[$id]) && $attendanceMap[$id];
            if ($hasAttendance) {
                $attended[$id] = $label;
            } else {
                $other[$id] = $label;
            }
        }

        return $attended + $other;
    }
}
