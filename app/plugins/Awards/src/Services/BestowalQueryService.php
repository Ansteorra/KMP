<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

/**
 * Builds configured ORM queries and grid-processing option arrays for bestowal grids.
 */
class BestowalQueryService
{
    /**
     * Build the base query and grid options for the main bestowals index.
     *
     * @param \Cake\ORM\Table $bestowalsTable The Bestowals ORM table.
     * @param bool $canEdit Whether the current user can manage bestowals.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array<string, mixed>}
     */
    public function buildIndexQuery(Table $bestowalsTable, bool $canEdit): array
    {
        $baseQuery = $bestowalsTable->find()
            ->leftJoinWith('Gatherings')
            ->leftJoinWith('Members')
            ->contain([
                'Members' => function ($query) {
                    return $query->select([
                        'id',
                        'sca_name',
                        'title',
                        'pronouns',
                        'pronunciation',
                        'additional_info',
                    ]);
                },
                'Gatherings' => function ($query) {
                    return $query->select(['id', 'name', 'start_date', 'end_date', 'cancelled_at']);
                },
                'GatheringScheduledActivities' => function ($query) {
                    return $query->select([
                        'id',
                        'gathering_id',
                        'gathering_activity_id',
                        'display_title',
                        'start_datetime',
                        'end_datetime',
                    ]);
                },
                'Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'name', 'branch_id', 'level_id']);
                },
                'Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
                'PrimaryRecommendation' => function ($query) {
                    return $query->select(['id', 'member_sca_name', 'award_id', 'state', 'status']);
                },
                'PrimaryRecommendation.Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'branch_id', 'level_id']);
                },
                'PrimaryRecommendation.Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
                'Recommendations' => function ($query) {
                    return $query->select(['id', 'award_id', 'member_sca_name', 'reason', 'specialty']);
                },
                'Recommendations.Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'branch_id', 'level_id']);
                },
                'Recommendations.Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
            ]);

        $systemViews = BestowalsGridColumns::getSystemViews([]);

        $gridOptions = [
            'gridKey' => 'Awards.Bestowals.index.main',
            'gridColumnsClass' => BestowalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Bestowals',
            'defaultSort' => ['Bestowals.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-bestowals-all',
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'enableBulkSelection' => $canEdit,
            'bulkSelectionDataFields' => [
                'member-id' => 'member_id',
            ],
            'bulkActions' => [
                [
                    'key' => 'bulk-edit',
                    'label' => 'Bulk Edit',
                    'icon' => 'bi-pencil-square',
                    'modalTarget' => '#bulkEditBestowalModal',
                ],
            ],
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Build the base query and grid options for gathering-scoped bestowal grids.
     *
     * @param \Cake\ORM\Table $bestowalsTable The Bestowals ORM table.
     * @param int $gatheringId Gathering ID to filter by.
     * @param bool $canEdit Whether the current user can manage bestowals.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array<string, mixed>}
     */
    public function buildGatheringBestowalsQuery(Table $bestowalsTable, int $gatheringId, bool $canEdit): array
    {
        $baseQuery = $bestowalsTable->find()
            ->where(['Bestowals.gathering_id' => $gatheringId])
            ->contain([
                'Members' => function ($query) {
                    return $query->select([
                        'id',
                        'sca_name',
                        'title',
                        'pronouns',
                        'pronunciation',
                        'additional_info',
                    ]);
                },
                'Gatherings' => function ($query) {
                    return $query->select(['id', 'name', 'start_date', 'end_date', 'cancelled_at']);
                },
                'GatheringScheduledActivities' => function ($query) {
                    return $query->select([
                        'id',
                        'gathering_id',
                        'gathering_activity_id',
                        'display_title',
                        'start_datetime',
                        'end_datetime',
                    ]);
                },
                'Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'name', 'branch_id', 'level_id']);
                },
                'Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
                'PrimaryRecommendation' => function ($query) {
                    return $query->select(['id', 'member_sca_name', 'award_id', 'state', 'status']);
                },
                'PrimaryRecommendation.Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'branch_id', 'level_id']);
                },
                'PrimaryRecommendation.Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
                'Recommendations' => function ($query) {
                    return $query->select(['id', 'award_id', 'member_sca_name', 'reason', 'specialty']);
                },
                'Recommendations.Awards' => function ($query) {
                    return $query->select(['id', 'abbreviation', 'branch_id', 'level_id']);
                },
                'Recommendations.Awards.Levels' => function ($query) {
                    return $query->select(['id', 'name']);
                },
            ]);

        $systemViews = BestowalsGridColumns::getSystemViews(['context' => 'gatheringBestowals']);

        $gridOptions = [
            'gridKey' => 'Awards.Bestowals.gathering.' . $gatheringId,
            'gridColumnsClass' => BestowalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Bestowals',
            'defaultSort' => ['Bestowals.stack_rank' => 'asc', 'Bestowals.id' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-bestowals-gathering',
            'showAllTab' => false,
            'showViewTabs' => true,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'enableBulkSelection' => $canEdit,
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Apply hidden-state visibility constraints to a bestowals query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Bestowals query.
     * @param bool $canViewHidden Whether hidden rows may be included.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function applyHiddenStateVisibility(SelectQuery $query, bool $canViewHidden): SelectQuery
    {
        if ($canViewHidden) {
            return $query;
        }

        $hiddenStates = Bestowal::getHiddenStates();
        if ($hiddenStates !== []) {
            $query->where(['Bestowals.state NOT IN' => $hiddenStates]);
        }

        return $query;
    }

    /**
     * Exclude terminal closed bestowals from an active-work queue query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Bestowals query.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function applyActiveOnlyFilter(SelectQuery $query): SelectQuery
    {
        return $query->where([
            'Bestowals.state NOT IN' => ['Given', 'Cancelled', 'Announced Not Given'],
        ]);
    }
}
