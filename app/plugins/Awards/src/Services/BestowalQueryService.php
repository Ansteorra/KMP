<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActionItem;
use Awards\KMP\GridColumns\BestowalsGridColumns;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

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
    public function buildIndexQuery(Table $bestowalsTable, bool $canEdit, ?array $visibleColumns = null): array
    {
        $contain = $this->bestowalContain($visibleColumns);
        $selectFields = $this->bestowalSelectFields($visibleColumns);

        $baseQuery = $bestowalsTable->find()
            ->leftJoinWith('Gatherings')
            ->leftJoinWith('Members')
            ->leftJoinWith('Awards');
        if ($selectFields !== null) {
            $baseQuery->select($selectFields);
        }
        $this->selectOpenTodoCount($baseQuery, $visibleColumns);
        $baseQuery->contain($contain);

        $systemViews = BestowalsGridColumns::getSystemViews([]);

        $gridOptions = [
            'gridKey' => 'Awards.Bestowals.index.main',
            'gridColumnsClass' => BestowalsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Bestowals',
            'defaultSort' => ['Bestowals.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-bestowals-active',
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
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
    public function buildGatheringBestowalsQuery(
        Table $bestowalsTable,
        int $gatheringId,
        bool $canEdit,
        ?array $visibleColumns = null,
    ): array {
        $contain = $this->bestowalContain($visibleColumns);
        $selectFields = $this->bestowalSelectFields($visibleColumns);

        $baseQuery = $bestowalsTable->find()
            ->leftJoinWith('Gatherings')
            ->leftJoinWith('Members')
            ->leftJoinWith('Awards')
            ->where(['Bestowals.gathering_id' => $gatheringId]);
        if ($selectFields !== null) {
            $baseQuery->select($selectFields);
        }
        $this->selectOpenTodoCount($baseQuery, $visibleColumns);
        $baseQuery->contain($contain);

        $systemViews = BestowalsGridColumns::getSystemViews(['context' => 'gatheringBestowals']);

        $gridOptions = [
            'gridKey' => 'Awards.Bestowals.gathering',
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
        ];

        return ['query' => $baseQuery, 'gridOptions' => $gridOptions];
    }

    /**
     * Apply hidden-state visibility constraints to a bestowals query.
     *
     * Lifecycle bestowals have no hidden states, so this is a pass-through retained
     * for call-site compatibility.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Bestowals query.
     * @param bool $canViewHidden Whether hidden rows may be included.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function applyHiddenStateVisibility(SelectQuery $query, bool $canViewHidden): SelectQuery
    {
        return $query;
    }

    /**
     * Exclude terminal (given/cancelled) bestowals from an active-work queue query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Bestowals query.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function applyActiveOnlyFilter(SelectQuery $query): SelectQuery
    {
        return $query->where([
            'Bestowals.lifecycle_status NOT IN' => [
                Bestowal::LIFECYCLE_GIVEN,
                Bestowal::LIFECYCLE_CANCELLED,
            ],
        ]);
    }

    /**
     * @param array<int,string>|null $visibleColumns
     * @return array<string,mixed>
     */
    private function bestowalContain(?array $visibleColumns): array
    {
        $contain = [];

        if ($this->shouldLoadColumn('member_sca_name', $visibleColumns)) {
            $contain['Members'] = function ($query) {
                return $query->select([
                    'id',
                    'sca_name',
                    'title',
                    'pronouns',
                    'pronunciation',
                    'additional_info',
                ]);
            };
        }

        if (
            $this->shouldLoadColumn('gathering_name', $visibleColumns)
            || $this->shouldLoadColumn('court_slot', $visibleColumns)
        ) {
            $contain['Gatherings'] = function ($query) {
                return $query->select([
                    'id',
                    'public_id',
                    'name',
                    'start_date',
                    'end_date',
                    'cancelled_at',
                ]);
            };
        }

        if ($this->shouldLoadColumn('court_slot', $visibleColumns)) {
            $contain['GatheringScheduledActivities'] = function ($query) {
                return $query->select([
                    'id',
                    'gathering_id',
                    'gathering_activity_id',
                    'display_title',
                    'start_datetime',
                    'end_datetime',
                ]);
            };
        }

        $needsAwards = $this->shouldLoadColumn('awards', $visibleColumns);
        $needsAwardType = $this->shouldLoadColumn('award_type', $visibleColumns);
        $needsAwardGroup = $this->shouldLoadColumn('award_group', $visibleColumns);
        $needsAwardRecord = $needsAwards || $needsAwardType || $needsAwardGroup;
        $needsRecommendationReasons = $this->shouldLoadColumn('recommendation_reasons', $visibleColumns);

        if ($needsAwardRecord) {
            $contain['Awards'] = function ($query) {
                return $query->select([
                    'id',
                    'abbreviation',
                    'name',
                    'domain_id',
                    'branch_id',
                    'level_id',
                ]);
            };
        }
        if ($needsAwards) {
            $contain['Awards.Levels'] = function ($query) {
                return $query->select(['id', 'name']);
            };
            $contain['PrimaryRecommendation'] = function ($query) {
                return $query->select(['id', 'member_sca_name', 'award_id', 'state', 'status']);
            };
            $contain['PrimaryRecommendation.Awards'] = function ($query) {
                return $query->select(['id', 'abbreviation', 'branch_id', 'level_id']);
            };
            $contain['PrimaryRecommendation.Awards.Levels'] = function ($query) {
                return $query->select(['id', 'name']);
            };
        }
        if ($needsAwardType) {
            $contain['Awards.Domains'] = function ($query) {
                return $query->select(['id', 'name']);
            };
        }
        if ($needsAwardGroup) {
            $contain['Awards.Branches'] = function ($query) {
                return $query->select(['id', 'name']);
            };
        }
        if ($needsAwards || $needsRecommendationReasons) {
            $contain['Recommendations.Awards'] = function ($query) {
                return $query->select(['id', 'abbreviation', 'name', 'branch_id', 'level_id']);
            };
            $contain['Recommendations.Awards.Levels'] = function ($query) {
                return $query->select(['id', 'name']);
            };
            $contain['Recommendations'] = function ($query) {
                return $query->select([
                    'id',
                    'award_id',
                    'member_sca_name',
                    'requester_sca_name',
                    'reason',
                    'specialty',
                ]);
            };
        }

        return $contain;
    }

    /**
     * @param array<int,string>|null $visibleColumns
     * @return array<int,string>|null
     */
    private function bestowalSelectFields(?array $visibleColumns): ?array
    {
        if ($visibleColumns === null) {
            return null;
        }

        $fields = [
            'Bestowals.id',
            'Bestowals.member_id',
            'Bestowals.gathering_id',
            'Bestowals.gathering_scheduled_activity_id',
            'Bestowals.primary_recommendation_id',
            'Bestowals.award_id',
            'Bestowals.lifecycle_status',
            'Bestowals.stack_rank',
            'Bestowals.bestowed_at',
            'Bestowals.source',
            'Bestowals.call_into_court',
            'Bestowals.court_availability',
            'Bestowals.person_to_notify',
            'Bestowals.roaming_court',
            'Bestowals.created',
            'Bestowals.modified',
            'Bestowals.deleted',
        ];

        if ($this->shouldLoadColumn('herald_notes_preview', $visibleColumns)) {
            $fields[] = 'Bestowals.herald_notes';
        }

        return $fields;
    }

    /**
     * @param array<int,string>|null $visibleColumns
     */
    private function shouldLoadColumn(string $columnKey, ?array $visibleColumns): bool
    {
        return $visibleColumns === null || in_array($columnKey, $visibleColumns, true);
    }

    /**
     * Project the open To-Do count used by the grid badge so that column can be sorted.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Bestowals query.
     * @param array<int,string>|null $visibleColumns Active query columns.
     * @return void
     */
    private function selectOpenTodoCount(SelectQuery $query, ?array $visibleColumns): void
    {
        if (!$this->shouldLoadColumn('todos_summary', $visibleColumns)) {
            return;
        }

        $actionItems = TableRegistry::getTableLocator()->get('ActionItems');
        $openTodoCount = $actionItems->find()
            ->select(['count' => $actionItems->find()->func()->count('*')])
            ->where([
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.status' => ActionItem::STATUS_OPEN,
                'ActionItems.entity_id = Bestowals.id',
            ]);

        $query->select(['open_todo_count' => $openTodoCount]);
    }
}
