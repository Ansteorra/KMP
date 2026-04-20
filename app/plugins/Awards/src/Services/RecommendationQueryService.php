<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\KMP\GridColumns\RecommendationsGridColumns;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

/**
 * Builds configured ORM queries and grid-processing option arrays for recommendation grids.
 *
 * Centralises the query-construction and option-building logic shared by the main grid,
 * member-submitted, recs-for-member, and gathering-awards grid endpoints. The controller
 * retains responsibility for authorization, view-variable assignment, and template rendering.
 *
 * @see \Awards\Controller\RecommendationsController::gridData()
 * @see \Awards\Controller\RecommendationsController::memberSubmittedRecsGridData()
 * @see \Awards\Controller\RecommendationsController::recsForMemberGridData()
 * @see \Awards\Controller\RecommendationsController::gatheringAwardsGridData()
 */
class RecommendationQueryService
{
    /**
     * Build the base query and grid options for the main recommendations index.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param bool $canEdit Whether the current user can edit recommendations (enables bulk actions).
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildMainGridQuery(Table $recommendationsTable, bool $canEdit): array
    {
        $baseQuery = $recommendationsTable->find()
            ->innerJoinWith('Awards.AwardBranch')
            ->leftJoinWith('Awards.Domains')
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name', 'type']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation', 'branch_id']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards.AwardBranch' => function ($q) {
                    return $q->select(['id', 'name', 'type']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name', 'cancelled_at']);
                },
            ]);

        $systemViews = RecommendationsGridColumns::getSystemViews([]);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.index.main',
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-all',
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'enableBulkSelection' => $canEdit,
            'bulkActions' => [
                [
                    'key' => 'bulk-edit',
                    'label' => 'Bulk Edit',
                    'icon' => 'bi-pencil-square',
                    'modalTarget' => '#bulkEditRecommendationModal',
                ],
            ],
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Build the base query and grid options for the member-submitted recommendations grid.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param int $memberId The requester member ID to filter by.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildMemberSubmittedQuery(Table $recommendationsTable, int $memberId): array
    {
        $baseQuery = $recommendationsTable->find()
            ->where(['Recommendations.requester_id' => $memberId])
            ->leftJoinWith('Awards.Domains')
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
            ]);

        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'memberSubmitted']);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.memberSubmitted.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 15,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-submitted-by',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'enableColumnPicker' => false,
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Build the base query and grid options for the recs-for-member grid.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param int $memberId The subject member ID to filter by.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildRecsForMemberQuery(Table $recommendationsTable, int $memberId): array
    {
        $baseQuery = $recommendationsTable->find()
            ->where(['Recommendations.member_id' => $memberId])
            ->leftJoinWith('Awards.Domains')
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name', 'cancelled_at']);
                },
            ]);

        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'recsForMember']);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.forMember.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 15,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-for-member',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Build the base query and grid options for the gathering-awards grid.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param int $gatheringId The gathering ID to filter by.
     * @param bool $canEdit Whether the current user can edit recommendations (enables bulk actions).
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildGatheringAwardsQuery(Table $recommendationsTable, int $gatheringId, bool $canEdit): array
    {
        $baseQuery = $recommendationsTable->find()
            ->where(['Recommendations.gathering_id' => $gatheringId])
            ->leftJoinWith('Awards.Domains')
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name', 'type']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation', 'branch_id']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name', 'cancelled_at']);
                },
            ]);

        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'gatheringAwards']);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.gathering.' . $gatheringId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.member_sca_name' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-gathering',
            'showAllTab' => false,
            'showViewTabs' => true,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'enableBulkSelection' => $canEdit,
            'bulkActions' => [
                [
                    'key' => 'bulk-edit',
                    'label' => 'Bulk Edit',
                    'icon' => 'bi-pencil-square',
                    'modalTarget' => '#bulkEditRecommendationModal',
                ],
            ],
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Apply hidden-state visibility constraints to a recommendations query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Recommendations query.
     * @param bool $canViewHidden Whether hidden rows may be included.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function applyHiddenStateVisibility(SelectQuery $query, bool $canViewHidden): SelectQuery
    {
        if ($canViewHidden) {
            return $query;
        }

        $hiddenStates = RecommendationsGridColumns::getHiddenStates();
        if (!empty($hiddenStates)) {
            $query->where(['Recommendations.state NOT IN' => $hiddenStates]);
        }

        return $query;
    }
}
