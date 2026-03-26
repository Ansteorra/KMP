<?php
declare(strict_types=1);

namespace Waivers\Services;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use DateTimeZone;

/**
 * Waiver Dashboard Service
 *
 * Aggregates metrics and data for the waiver secretary dashboard: statistics,
 * calendar data, gatherings needing waivers, and compliance tracking.
 */
class WaiverDashboardService
{
    /**
     * Get dashboard statistics.
     *
     * @param array $branchIds Branches to include in statistics
     * @return array Statistics data
     */
    public function getDashboardStatistics(array $branchIds): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $thirtyDaysAgo = Date::now()->subDays(30);

        $totalWaivers = $GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where(['GatheringWaivers.deleted IS' => null])
            ->count();

        $recentWaivers = $GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.created >=' => $thirtyDaysAgo->toDateString(),
            ])
            ->count();

        $declinedWaivers = $GatheringWaivers->find()
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.declined_at IS NOT' => null,
            ])
            ->count();

        $waiverGatherings = $this->getGatheringsWithIncompleteWaivers($branchIds, 30);
        $gatheringsMissingCount = count(array_filter(
            $waiverGatherings['missing'],
            fn($gathering) => ($gathering->uploaded_waiver_count ?? 0) === 0,
        ));
        $gatheringsNeedingCount = count($waiverGatherings['upcoming']);

        $branchesWithGatherings = $Gatherings->find()
            ->where([
                'branch_id IN' => $branchIds,
                'deleted IS' => null,
                'start_date >=' => $thirtyDaysAgo->toDateString(),
            ])
            ->select(['branch_id'])
            ->distinct(['branch_id'])
            ->count();

        return [
            'totalWaivers' => $totalWaivers,
            'recentWaivers' => $recentWaivers,
            'declinedWaivers' => $declinedWaivers,
            'gatheringsMissingCount' => $gatheringsMissingCount,
            'gatheringsNeedingCount' => $gatheringsNeedingCount,
            'branchesWithGatherings' => $branchesWithGatherings,
        ];
    }

    /**
     * Get gatherings with incomplete waivers, separated by status.
     *
     * @param array $branchIds Branches to check
     * @param int $daysAhead How many days ahead to look for upcoming events
     * @return array Array with 'missing' and 'upcoming' keys
     */
    public function getGatheringsWithIncompleteWaivers(array $branchIds, int $daysAhead): array
    {
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
        $today = Date::now();
        $todayString = $today->toDateString();
        $futureDate = DateTime::now()->addDays($daysAhead)->endOfDay()->format('Y-m-d H:i:s');
        $pastCutoff = Date::now()->subDays(90)->toDateString();

        $complianceDays = (int)StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', 'int', false);

        $query = $Gatherings->find()
            ->where([
                'OR' => [
                    'Gatherings.end_date >=' => $todayString,
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $todayString,
                    ],
                    'Gatherings.end_date >=' => $pastCutoff,
                ],
                'Gatherings.start_date <=' => $futureDate,
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
            ])
            ->contain([
                'Branches',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }

        $allGatherings = $query->all()->toArray();
        if (empty($allGatherings)) {
            return [
                'missing' => [],
                'upcoming' => [],
            ];
        }

        $gatheringsMissing = [];
        $gatheringsUpcoming = [];

        $gatheringIds = [];
        $activityToGatheringMap = [];
        foreach ($allGatherings as $gathering) {
            $gatheringIds[] = (int)$gathering->id;

            foreach ($gathering->gathering_activities ?? [] as $activity) {
                $activityToGatheringMap[(int)$activity->id][(int)$gathering->id] = true;
            }
        }

        // Batch 1: required waiver types by gathering
        $requiredTypeIdsByGathering = [];
        if (!empty($activityToGatheringMap)) {
            $requiredRows = $GatheringActivityWaivers->find()
                ->where([
                    'gathering_activity_id IN' => array_keys($activityToGatheringMap),
                    'deleted IS' => null,
                ])
                ->select([
                    'gathering_activity_id',
                    'waiver_type_id',
                ])
                ->distinct(['gathering_activity_id', 'waiver_type_id'])
                ->all();

            foreach ($requiredRows as $row) {
                $activityId = (int)$row->get('gathering_activity_id');
                $waiverTypeId = (int)$row->get('waiver_type_id');
                $gatheringIdsForActivity = array_keys($activityToGatheringMap[$activityId] ?? []);
                foreach ($gatheringIdsForActivity as $gatheringId) {
                    $requiredTypeIdsByGathering[$gatheringId][$waiverTypeId] = true;
                }
            }
        }

        // Batch 2: uploaded waiver types by gathering
        $uploadedTypeIdsByGathering = [];
        if (!empty($gatheringIds)) {
            $uploadedRows = $GatheringWaivers->find()
                ->where([
                    'gathering_id IN' => $gatheringIds,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                ])
                ->select([
                    'gathering_id',
                    'waiver_type_id',
                ])
                ->distinct(['gathering_id', 'waiver_type_id'])
                ->all();

            foreach ($uploadedRows as $row) {
                $gatheringId = (int)$row->get('gathering_id');
                $waiverTypeId = (int)$row->get('waiver_type_id');
                $uploadedTypeIdsByGathering[$gatheringId][$waiverTypeId] = true;
            }
        }

        $statsByGathering = [];
        $allWaiverTypeIds = [];
        foreach ($allGatherings as $gathering) {
            $gatheringId = (int)$gathering->id;
            $requiredWaiverTypes = array_map(
                'intval',
                array_keys($requiredTypeIdsByGathering[$gatheringId] ?? []),
            );
            if (empty($requiredWaiverTypes)) {
                continue;
            }

            $uploadedWaiverTypes = array_map(
                'intval',
                array_keys($uploadedTypeIdsByGathering[$gatheringId] ?? []),
            );
            $missingWaiverTypes = array_values(array_diff($requiredWaiverTypes, $uploadedWaiverTypes));
            if (empty($missingWaiverTypes)) {
                continue;
            }

            $statsByGathering[$gatheringId] = [
                'required_type_ids' => $requiredWaiverTypes,
                'uploaded_type_ids' => $uploadedWaiverTypes,
                'missing_type_ids' => $missingWaiverTypes,
            ];

            foreach (array_merge($uploadedWaiverTypes, $missingWaiverTypes) as $waiverTypeId) {
                $allWaiverTypeIds[(int)$waiverTypeId] = true;
            }
        }

        // Batch 3: resolve waiver type names
        $waiverTypeNameMap = [];
        if (!empty($allWaiverTypeIds)) {
            $waiverTypes = $WaiverTypes->find()
                ->where(['id IN' => array_keys($allWaiverTypeIds)])
                ->select(['id', 'name'])
                ->all();

            foreach ($waiverTypes as $waiverType) {
                $waiverTypeNameMap[(int)$waiverType->id] = (string)$waiverType->name;
            }
        }

        foreach ($allGatherings as $gathering) {
            $gatheringId = (int)$gathering->id;
            if (empty($statsByGathering[$gatheringId])) {
                continue;
            }

            $uploadedWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gatheringId]['uploaded_type_ids'],
            )));
            sort($uploadedWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $missingWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gatheringId]['missing_type_ids'],
            )));
            sort($missingWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $gathering->missing_waiver_count = count($statsByGathering[$gatheringId]['missing_type_ids']);
            $gathering->missing_waiver_names = $missingWaiverNames;
            $gathering->uploaded_waiver_count = count($statsByGathering[$gatheringId]['uploaded_type_ids']);
            $gathering->uploaded_waiver_names = $uploadedWaiverNames;

            $endDate = $gathering->end_date ? Date::parse($gathering->end_date) : Date::parse($gathering->start_date);
            $daysAfterEnd = $today->diffInDays($endDate, false);

            if ($daysAfterEnd < -$complianceDays) {
                $gatheringsMissing[] = $gathering;
            } else {
                $gatheringsUpcoming[] = $gathering;
            }
        }

        return [
            'missing' => $gatheringsMissing,
            'upcoming' => $gatheringsUpcoming,
        ];
    }

    /**
     * Search for waivers across all accessible gatherings.
     *
     * @param string $searchTerm Search term
     * @param array $branchIds Branches the user can access
     * @return array Search results
     */
    public function searchWaivers(string $searchTerm, array $branchIds): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');

        $query = $GatheringWaivers->find()
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'OR' => [
                    'Gatherings.name LIKE' => '%' . $searchTerm . '%',
                    'Branches.name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.sca_name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.first_name LIKE' => '%' . $searchTerm . '%',
                    'CreatedByMembers.last_name LIKE' => '%' . $searchTerm . '%',
                    'WaiverTypes.name LIKE' => '%' . $searchTerm . '%',
                ],
            ])
            ->innerJoinWith('Gatherings.Branches', function ($q) use ($branchIds) {
                return $q->where([
                    'Branches.id IN' => $branchIds,
                    'Branches.deleted IS' => null,
                ]);
            })
            ->innerJoinWith('Gatherings', function ($q) {
                return $q->where([
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CreatedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name', 'first_name', 'last_name']);
                },
            ])
            ->orderBy(['GatheringWaivers.created' => 'DESC'])
            ->limit(50);

        return $query->all()->toArray();
    }

    /**
     * Get branches with compliance issues.
     *
     * @param array $branchIds Branches to check
     * @return array Branches with issue counts
     */
    public function getBranchesWithIssues(array $branchIds): array
    {
        $waiverGatherings = $this->getGatheringsWithIncompleteWaivers($branchIds, 60);
        $allGatheringsWithIssues = $waiverGatherings['missing'];

        $branchIssues = [];
        foreach ($allGatheringsWithIssues as $gathering) {
            $branchId = $gathering->branch_id;
            if (!isset($branchIssues[$branchId])) {
                $branchIssues[$branchId] = [
                    'branch' => $gathering->branch,
                    'gathering_count' => 0,
                    'total_missing_waivers' => 0,
                ];
            }
            $branchIssues[$branchId]['gathering_count']++;
            $branchIssues[$branchId]['total_missing_waivers'] += $gathering->missing_waiver_count;
        }

        usort($branchIssues, function ($a, $b) {
            return $b['gathering_count'] <=> $a['gathering_count'];
        });

        return array_slice($branchIssues, 0, 10);
    }

    /**
     * Get recent waiver activity.
     *
     * @param array $branchIds Branches to include
     * @param int $days Days to look back
     * @return array Recent waivers
     */
    public function getRecentWaiverActivity(array $branchIds, int $days): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $sinceDate = Date::now()->subDays($days);

        $query = $GatheringWaivers->find()
            ->where([
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.created >=' => $sinceDate->toDateString(),
            ])
            ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                return $q->where([
                    'Gatherings.branch_id IN' => $branchIds,
                    'Gatherings.deleted IS' => null,
                ]);
            })
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'branch_id']);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'WaiverTypes' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CreatedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->orderBy(['GatheringWaivers.created' => 'DESC'])
            ->limit(20);

        return $query->all()->toArray();
    }

    /**
     * Get summary of waivers by type.
     *
     * @param array $branchIds Branches to include
     * @return array Waiver type counts
     */
    public function getWaiverTypesSummary(array $branchIds): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');

        $waiverTypes = $WaiverTypes->find()
            ->where([
                'is_active' => true,
                'deleted IS' => null,
            ])
            ->orderBy(['name' => 'ASC'])
            ->all();

        $summary = [];
        foreach ($waiverTypes as $waiverType) {
            $count = $GatheringWaivers->find()
                ->innerJoinWith('Gatherings', function ($q) use ($branchIds) {
                    return $q->where([
                        'Gatherings.branch_id IN' => $branchIds,
                        'Gatherings.deleted IS' => null,
                    ]);
                })
                ->where([
                    'GatheringWaivers.waiver_type_id' => $waiverType->id,
                    'GatheringWaivers.deleted IS' => null,
                ])
                ->count();

            $summary[] = [
                'waiver_type' => $waiverType,
                'count' => $count,
            ];
        }

        return $summary;
    }

    /**
     * Get gatherings that have been marked ready to close by event staff.
     *
     * @param array $branchIds Branches the user can access
     * @return array Gatherings ready to close
     */
    public function getGatheringsReadyToClose(array $branchIds): array
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');

        $readyClosures = $GatheringWaiverClosures->find()
            ->where([
                'ready_to_close_at IS NOT' => null,
                'closed_at IS' => null,
            ])
            ->contain(['ReadyToCloseByMembers'])
            ->all()
            ->indexBy('gathering_id')
            ->toArray();

        if (empty($readyClosures)) {
            return [];
        }

        $readyGatheringIds = array_keys($readyClosures);

        $gatherings = $Gatherings->find()
            ->where([
                'Gatherings.id IN' => $readyGatheringIds,
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
            ])
            ->contain([
                'Branches',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->all();

        $result = [];

        foreach ($gatherings as $gathering) {
            $closure = $readyClosures[$gathering->id] ?? null;
            $gathering->ready_to_close_at = $closure?->ready_to_close_at;
            $gathering->ready_to_close_by_member = $closure?->ready_to_close_by_member;

            $gathering->missing_waiver_count = 0;
            $gathering->missing_waiver_names = [];
            $gathering->is_waiver_complete = true;

            if (!empty($gathering->gathering_activities)) {
                $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();

                $requiredWaiverTypes = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->all()
                    ->extract('waiver_type_id')
                    ->toArray();

                if (!empty($requiredWaiverTypes)) {
                    $uploadedWaiverTypes = $GatheringWaivers->find()
                        ->where([
                            'gathering_id' => $gathering->id,
                            'deleted IS' => null,
                            'declined_at IS' => null,
                        ])
                        ->select(['waiver_type_id'])
                        ->distinct(['waiver_type_id'])
                        ->all()
                        ->extract('waiver_type_id')
                        ->toArray();

                    $missingWaiverTypes = array_diff($requiredWaiverTypes, $uploadedWaiverTypes);

                    if (!empty($missingWaiverTypes)) {
                        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
                        $missingWaiverNames = $WaiverTypes->find()
                            ->where(['id IN' => $missingWaiverTypes])
                            ->orderBy(['name' => 'ASC'])
                            ->all()
                            ->extract('name')
                            ->toArray();

                        $gathering->missing_waiver_count = count($missingWaiverTypes);
                        $gathering->missing_waiver_names = $missingWaiverNames;
                        $gathering->is_waiver_complete = false;
                    }
                }
            }

            $result[] = $gathering;
        }

        return $result;
    }

    /**
     * Get gatherings that need to be closed (have waivers uploaded but not yet closed).
     *
     * @param array $branchIds Branches to check
     * @return array Gatherings needing closure
     */
    public function getGatheringsNeedingClosed(array $branchIds): array
    {
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
        $today = Date::now();
        $pastCutoff = Date::now()->subDays(180)->toDateString();

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        $query = $Gatherings->find()
            ->where([
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'Gatherings.end_date <' => $today->toDateString(),
                'Gatherings.end_date >=' => $pastCutoff,
            ])
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.end_date' => 'ASC']);

        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }
        if (!empty($readyToCloseGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $readyToCloseGatheringIds]);
        }

        $gatherings = $query->all()->toArray();
        if (empty($gatherings)) {
            return [];
        }

        $gatheringIds = array_map(
            static fn($gathering) => (int)$gathering->id,
            $gatherings,
        );

        // Batch 1: required waiver types by gathering
        $requiredRows = $GatheringActivityWaivers->find()
            ->innerJoinWith('GatheringActivities.Gatherings', function ($q) use ($gatheringIds) {
                return $q->where([
                    'Gatherings.id IN' => $gatheringIds,
                ]);
            })
            ->where([
                'GatheringActivityWaivers.deleted IS' => null,
                'GatheringActivities.deleted IS' => null,
            ])
            ->select([
                'gathering_id' => 'Gatherings.id',
                'waiver_type_id' => 'GatheringActivityWaivers.waiver_type_id',
            ])
            ->distinct(['Gatherings.id', 'GatheringActivityWaivers.waiver_type_id'])
            ->all();

        $requiredTypeIdsByGathering = [];
        foreach ($requiredRows as $row) {
            $gid = (int)$row->get('gathering_id');
            $waiverTypeId = (int)$row->get('waiver_type_id');
            $requiredTypeIdsByGathering[$gid][$waiverTypeId] = true;
        }

        // Batch 2: uploaded/exempted waiver types by gathering
        $uploadedRows = $GatheringWaivers->find()
            ->where([
                'GatheringWaivers.gathering_id IN' => $gatheringIds,
                'GatheringWaivers.deleted IS' => null,
                'GatheringWaivers.declined_at IS' => null,
            ])
            ->select([
                'gathering_id',
                'waiver_type_id',
            ])
            ->distinct(['gathering_id', 'waiver_type_id'])
            ->all();

        $uploadedTypeIdsByGathering = [];
        foreach ($uploadedRows as $row) {
            $gid = (int)$row->get('gathering_id');
            $waiverTypeId = (int)$row->get('waiver_type_id');
            $uploadedTypeIdsByGathering[$gid][$waiverTypeId] = true;
        }

        $statsByGathering = [];
        $allWaiverTypeIds = [];

        foreach ($gatherings as $gathering) {
            $gid = (int)$gathering->id;
            $uploadedTypeIds = array_map('intval', array_keys($uploadedTypeIdsByGathering[$gid] ?? []));

            if (empty($uploadedTypeIds)) {
                continue;
            }

            $requiredTypeIds = array_map('intval', array_keys($requiredTypeIdsByGathering[$gid] ?? []));
            $missingTypeIds = array_values(array_diff($requiredTypeIds, $uploadedTypeIds));

            $statsByGathering[$gid] = [
                'uploaded_type_ids' => $uploadedTypeIds,
                'missing_type_ids' => $missingTypeIds,
                'is_complete' => empty($missingTypeIds),
            ];

            foreach (array_merge($uploadedTypeIds, $missingTypeIds) as $waiverTypeId) {
                $allWaiverTypeIds[(int)$waiverTypeId] = true;
            }
        }

        // Batch 3: resolve all waiver type names
        $waiverTypeNameMap = [];
        if (!empty($allWaiverTypeIds)) {
            $waiverTypes = $WaiverTypes->find()
                ->where(['id IN' => array_keys($allWaiverTypeIds)])
                ->select(['id', 'name'])
                ->all();

            foreach ($waiverTypes as $waiverType) {
                $waiverTypeNameMap[(int)$waiverType->id] = (string)$waiverType->name;
            }
        }

        $result = [];
        foreach ($gatherings as $gathering) {
            $gid = (int)$gathering->id;
            if (empty($statsByGathering[$gid])) {
                continue;
            }

            $uploadedWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gid]['uploaded_type_ids'],
            )));
            sort($uploadedWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $missingWaiverNames = array_values(array_filter(array_map(
                static fn($waiverTypeId) => $waiverTypeNameMap[(int)$waiverTypeId] ?? null,
                $statsByGathering[$gid]['missing_type_ids'],
            )));
            sort($missingWaiverNames, SORT_NATURAL | SORT_FLAG_CASE);

            $gathering->uploaded_waiver_count = count($statsByGathering[$gid]['uploaded_type_ids']);
            $gathering->uploaded_waiver_names = $uploadedWaiverNames;
            $gathering->missing_waiver_count = count($statsByGathering[$gid]['missing_type_ids']);
            $gathering->missing_waiver_names = $missingWaiverNames;
            $gathering->is_waiver_complete = (bool)$statsByGathering[$gid]['is_complete'];

            $result[] = $gathering;
        }

        return $result;
    }

    /**
     * Get recently closed gatherings.
     *
     * @param array $branchIds Branches to check
     * @return array Closed gatherings with closure details
     */
    public function getClosedGatherings(array $branchIds): array
    {
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $ninetyDaysAgo = Date::now()->subDays(90)->toDateString();

        $closures = $GatheringWaiverClosures->find()
            ->where([
                'GatheringWaiverClosures.closed_at IS NOT' => null,
                'GatheringWaiverClosures.closed_at >=' => $ninetyDaysAgo,
            ])
            ->contain([
                'Gatherings' => function ($q) use ($branchIds) {
                    return $q->where([
                        'Gatherings.branch_id IN' => $branchIds,
                        'Gatherings.deleted IS' => null,
                    ]);
                },
                'Gatherings.Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'ClosedByMembers' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->orderBy(['GatheringWaiverClosures.closed_at' => 'DESC'])
            ->all()
            ->toArray();

        $closures = array_filter($closures, function ($closure) {
            return $closure->gathering !== null;
        });

        return array_values($closures);
    }

    /**
     * Build calendar data for gatherings in a given month.
     *
     * @param int $year Calendar year
     * @param int $month Calendar month (1-12)
     * @param array $branchIds Branches the user can access
     * @param mixed $currentUser Current user identity for timezone
     * @return array Calendar data with events
     */
    public function getCalendarData(int $year, int $month, array $branchIds, $currentUser): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');

        $userTimezone = TimezoneHelper::getUserTimezone($currentUser);
        $timezone = new DateTimeZone($userTimezone);

        $startOfMonth = new DateTime(sprintf('%04d-%02d-01', $year, $month), $timezone);
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = (clone $startOfMonth)->modify('last day of this month')->setTime(23, 59, 59);

        $startOfMonthUtc = TimezoneHelper::toUtc($startOfMonth->format('Y-m-d H:i:s'), $userTimezone);
        $endOfMonthUtc = TimezoneHelper::toUtc($endOfMonth->format('Y-m-d H:i:s'), $userTimezone);
        $startUtcString = $startOfMonthUtc->format('Y-m-d H:i:s');
        $endUtcString = $endOfMonthUtc->format('Y-m-d H:i:s');

        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        $gatherings = $Gatherings->find()
            ->where([
                'Gatherings.branch_id IN' => $branchIds,
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
                'Gatherings.start_date <=' => $endUtcString,
                'OR' => [
                    'Gatherings.end_date >=' => $startUtcString,
                    'AND' => [
                        'Gatherings.end_date IS' => null,
                        'Gatherings.start_date >=' => $startUtcString,
                    ],
                ],
            ])
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC'])
            ->all();

        $events = [];
        foreach ($gatherings as $gathering) {
            $isClosed = in_array($gathering->id, $closedGatheringIds);

            $activityIds = collection($gathering->gathering_activities)->extract('id')->toArray();
            $requiredCount = 0;
            if (!empty($activityIds)) {
                $requiredCount = $GatheringActivityWaivers->find()
                    ->where([
                        'gathering_activity_id IN' => $activityIds,
                        'deleted IS' => null,
                    ])
                    ->select(['waiver_type_id'])
                    ->distinct(['waiver_type_id'])
                    ->count();
            }

            if ($requiredCount === 0 && !$isClosed) {
                continue;
            }

            $uploadedCount = $GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                    'is_exemption' => false,
                ])
                ->count();

            $exemptedCount = $GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                    'is_exemption' => true,
                ])
                ->count();

            $uploadedTypeCount = $GatheringWaivers->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'deleted IS' => null,
                    'declined_at IS' => null,
                ])
                ->select(['waiver_type_id'])
                ->distinct(['waiver_type_id'])
                ->count();

            $pendingCount = max(0, $requiredCount - $uploadedTypeCount);

            $status = 'missing';
            $color = 'danger';
            if ($isClosed) {
                $status = 'closed';
                $color = 'primary';
            } elseif ($requiredCount > 0 && $uploadedTypeCount >= $requiredCount) {
                $status = 'complete';
                $color = 'success';
            } elseif ($uploadedTypeCount > 0) {
                $status = 'partial';
                $color = 'warning';
            }

            $startLocal = TimezoneHelper::toUserTimezone($gathering->start_date, $currentUser, null, $gathering);
            $endLocal = TimezoneHelper::toUserTimezone(
                $gathering->end_date ?? $gathering->start_date,
                $currentUser,
                null,
                $gathering,
            );
            if ($startLocal === null || $endLocal === null) {
                continue;
            }

            $startDate = $startLocal->format('Y-m-d');
            $endDate = $endLocal->format('Y-m-d');
            $isMultiDay = $startDate !== $endDate;

            $events[] = [
                'id' => $gathering->id,
                'name' => $gathering->name,
                'branch' => $gathering->branch ? $gathering->branch->name : '',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'multi_day' => $isMultiDay,
                'status' => $status,
                'color' => $color,
                'uploaded' => $uploadedCount,
                'exempted' => $exemptedCount,
                'pending' => $pendingCount,
                'ready_to_close' => in_array($gathering->id, $readyToCloseGatheringIds),
                'url' => Router::url([
                    'plugin' => 'Waivers',
                    'controller' => 'GatheringWaivers',
                    'action' => 'index',
                    '?' => ['gathering_id' => $gathering->id],
                ]),
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'monthName' => $startOfMonth->format('F Y'),
            'events' => $events,
        ];
    }

    /**
     * Get needingWaivers data: gatherings that need waivers uploaded.
     *
     * @param array $branchIds Branch IDs user can access
     * @param array $stewardGatheringIds Gathering IDs where user is steward
     * @return array With 'gatherings' and 'incompleteCount' keys
     */
    public function getNeedingWaiversData(array $branchIds, array $stewardGatheringIds): array
    {
        $GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $GatheringWaiverClosures = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $Gatherings = TableRegistry::getTableLocator()->get('Gatherings');
        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');

        $today = Date::now()->toDateString();
        $oneWeekFromNow = Date::now()->addDays(7)->toDateString();
        $closedGatheringIds = $GatheringWaiverClosures->getClosedGatheringIds();
        $readyToCloseGatheringIds = $GatheringWaiverClosures->getReadyToCloseGatheringIds();

        // Build the access condition: branch-based OR steward-based
        $accessConditions = [];
        if (!empty($branchIds)) {
            $accessConditions[] = ['Gatherings.branch_id IN' => $branchIds];
        }
        if (!empty($stewardGatheringIds)) {
            $accessConditions[] = ['Gatherings.id IN' => $stewardGatheringIds];
        }

        if (empty($accessConditions)) {
            return ['gatherings' => [], 'incompleteCount' => 0];
        }

        $query = $Gatherings->find()
            ->where([
                'OR' => [
                    'Gatherings.start_date <' => $today,
                    'Gatherings.start_date <=' => $oneWeekFromNow,
                ],
                'Gatherings.deleted IS' => null,
                'Gatherings.cancelled_at IS' => null,
            ])
            ->where(['OR' => $accessConditions])
            ->contain([
                'Branches',
                'GatheringTypes',
                'GatheringActivities' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC', 'Gatherings.name' => 'ASC']);

        if (!empty($closedGatheringIds)) {
            $query->where(['Gatherings.id NOT IN' => $closedGatheringIds]);
        }

        $allGatherings = $query->all();

        // Batch-compute waiver status
        $gatheringIds = collection($allGatherings)->extract('id')->toArray();
        $gatherings = [];
        $incompleteCount = 0;

        if (!empty($gatheringIds)) {
            // Batch 1: required waiver types per gathering
            $conn = $GatheringActivityWaivers->getConnection();
            $requiredRows = $conn->execute(
                'SELECT DISTINCT GGA.gathering_id, GAW.waiver_type_id
                 FROM gatherings_gathering_activities GGA
                 INNER JOIN waivers_gathering_activity_waivers GAW
                    ON GAW.gathering_activity_id = GGA.gathering_activity_id
                    AND GAW.deleted IS NULL
                 WHERE GGA.gathering_id IN (' . implode(',', array_fill(0, count($gatheringIds), '?')) . ')',
                $gatheringIds,
            )->fetchAll('assoc');
            $requiredByGathering = [];
            foreach ($requiredRows as $row) {
                $requiredByGathering[(int)$row['gathering_id']][] = (int)$row['waiver_type_id'];
            }

            // Batch 2: uploaded waiver types per gathering
            $gatheringsWithReqs = array_keys($requiredByGathering);
            $uploadedByGathering = [];
            if (!empty($gatheringsWithReqs)) {
                $uploadedRows = $GatheringWaivers->find()
                    ->where([
                        'gathering_id IN' => $gatheringsWithReqs,
                        'deleted IS' => null,
                        'declined_at IS' => null,
                    ])
                    ->select(['gathering_id', 'waiver_type_id'])
                    ->distinct(['gathering_id', 'waiver_type_id'])
                    ->disableHydration()
                    ->all();
                foreach ($uploadedRows as $row) {
                    $uploadedByGathering[(int)$row['gathering_id']][] = (int)$row['waiver_type_id'];
                }
            }

            // Batch 3: waiver type names
            $allWaiverTypeIds = array_unique(array_merge(...array_values($requiredByGathering)));
            $waiverTypeNames = [];
            if (!empty($allWaiverTypeIds)) {
                $waiverTypeNames = $WaiverTypes->find()
                    ->where(['id IN' => $allWaiverTypeIds])
                    ->select(['id', 'name'])
                    ->disableHydration()
                    ->all()
                    ->combine('id', 'name')
                    ->toArray();
            }

            // Merge results
            foreach ($allGatherings as $gathering) {
                $gid = $gathering->id;
                $required = $requiredByGathering[$gid] ?? [];

                if (empty($required)) {
                    continue;
                }

                $uploaded = $uploadedByGathering[$gid] ?? [];
                $missing = array_diff($required, $uploaded);

                $gathering->has_waiver_requirements = true;
                $gathering->is_ready_to_close = in_array($gid, $readyToCloseGatheringIds, true);
                $gathering->uploaded_waiver_count = count($uploaded);
                $gathering->missing_waiver_count = count($missing);
                $gathering->missing_waiver_names = array_values(array_intersect_key(
                    $waiverTypeNames,
                    array_flip($missing),
                ));
                $gathering->is_waiver_complete = empty($missing);

                if (!empty($missing)) {
                    $incompleteCount++;
                }

                $gatherings[] = $gathering;
            }
        }

        return ['gatherings' => $gatherings, 'incompleteCount' => $incompleteCount];
    }
}
