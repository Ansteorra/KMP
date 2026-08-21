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
 * member-submitted, and recs-for-member grid endpoints. The controller
 * retains responsibility for authorization, view-variable assignment, and template rendering.
 *
 * @see \Awards\Controller\RecommendationsController::gridData()
 * @see \Awards\Controller\RecommendationsController::memberSubmittedRecsGridData()
 * @see \Awards\Controller\RecommendationsController::recsForMemberGridData()
 */
class RecommendationQueryService
{
    /**
     * Build the base query and grid options for the main recommendations index.
     *
     * @param \Cake\ORM\Table $recommendationsTable The Recommendations ORM table.
     * @param bool $canEdit Whether the current user can edit recommendations (enables bulk actions).
     * @param bool $includeNotes Whether notes relations should be loaded for list rendering.
     * @param array<int,string>|null $visibleColumns Resolved visible columns, or null when all display data is required.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildMainGridQuery(
        Table $recommendationsTable,
        bool $canEdit,
        bool $includeNotes = true,
        ?array $visibleColumns = null,
    ): array {
        $includeGatherings = $this->shouldLoadDisplayColumn('gatherings', $visibleColumns);
        $includeAssignedGathering = $this->shouldLoadDisplayColumn('assigned_gathering', $visibleColumns);
        $selectFields = $this->recommendationSelectFields($visibleColumns);

        $contain = [
            'Requesters' => function ($q) {
                return $q->select(['id', 'sca_name']);
            },
            'Members' => function ($q) {
                return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation', 'additional_info']);
            },
            'Branches' => function ($q) {
                return $q->select(['id', 'name', 'type']);
            },
            'Awards' => function ($q) {
                return $q->select(['id', 'abbreviation', 'branch_id', 'level_id']);
            },
            'Awards.Domains' => function ($q) {
                return $q->select(['id', 'name']);
            },
            'Awards.Levels' => function ($q) {
                return $q->select(['id', 'name']);
            },
            'Awards.AwardBranch' => function ($q) {
                return $q->select(['id', 'name', 'type']);
            },
            'Bestowals' => function ($q) {
                return $q->select(['id', 'lifecycle_status']);
            },
        ];
        if ($includeGatherings) {
            $contain['Gatherings'] = function ($q) {
                return $q->select(['id', 'name', 'start_date', 'end_date']);
            };
        }
        if ($includeAssignedGathering) {
            $contain['AssignedGathering'] = function ($q) {
                return $q->select(['id', 'name', 'cancelled_at']);
            };
        }
        $contain['CurrentApprovalRun'] = function ($q) {
            return $q->select([
                'id',
                'recommendation_id',
                'workflow_instance_id',
                'status',
                'current_step_key',
                'current_step_label',
            ]);
        };
        if ($includeNotes) {
            $contain['Notes'] = function ($q) {
                return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
            };
            $contain['Notes.Authors'] = function ($q) {
                return $q->select(['id', 'sca_name']);
            };
        }

        $baseQuery = $recommendationsTable->find()
            ->innerJoinWith('Awards.AwardBranch')
            ->where(['Recommendations.recommendation_group_id IS' => null])
            ->leftJoinWith('Awards.Domains')
            ->leftJoinWith('Bestowals')
            ->leftJoinWith('CurrentApprovalRun')
            ->innerJoinWith('Awards.Levels');
        if ($selectFields !== null) {
            $baseQuery->select($selectFields);
        }
        $baseQuery->contain($contain);

        $systemViews = RecommendationsGridColumns::getSystemViews([]);

        $bulkActions = [
            [
                'key' => 'workflow-decision',
                'label' => 'Approval Decision',
                'icon' => 'bi-check2-circle',
                'modalTarget' => '#recommendationWorkflowDecisionModal',
                'requiresSelectionField' => 'canWorkflowDecide',
            ],
        ];
        if ($canEdit) {
            $bulkActions = array_merge($bulkActions, [
                [
                    'key' => 'group-recs',
                    'label' => 'Group',
                    'icon' => 'bi-collection',
                    'modalTarget' => '#groupRecommendationsModal',
                ],
                [
                    'key' => 'request-feedback',
                    'label' => 'Request Feedback',
                    'icon' => 'bi-chat-left-text',
                    'modalTarget' => '#requestRecommendationFeedbackModal',
                ],
            ]);
        }

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.index.main',
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-in-approval',
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'enableBulkSelection' => true,
            'bulkSelectionDataFields' => [
                'member-id' => 'member_id',
                'bestowal-id' => 'bestowal_id',
                'pending-approval-id' => 'pending_approval_id',
                'can-workflow-decide' => 'can_workflow_decide',
            ],
            'bulkSelection' => $this->recommendationBulkSelectionConfig(),
            'bulkActions' => $bulkActions,
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
    public function buildMemberSubmittedQuery(
        Table $recommendationsTable,
        int $memberId,
        ?array $visibleColumns = null,
    ): array {
        $includeGatherings = $this->shouldLoadDisplayColumn('gatherings', $visibleColumns);
        $selectFields = $this->recommendationSelectFields($visibleColumns);

        $contain = [
            'Members' => function ($q) {
                return $q->select(['id', 'sca_name', 'additional_info']);
            },
            'Awards' => function ($q) {
                return $q->select(['id', 'abbreviation', 'branch_id', 'level_id']);
            },
            'Awards.Levels' => function ($q) {
                return $q->select(['id', 'name']);
            },
            'Awards.AwardBranch' => function ($q) {
                return $q->select(['id', 'name', 'type']);
            },
            'Bestowals' => function ($q) {
                return $q->select(['id', 'lifecycle_status']);
            },
        ];
        if ($includeGatherings) {
            $contain['Gatherings'] = function ($q) {
                return $q->select(['id', 'name', 'start_date', 'end_date']);
            };
        }
        $contain['CurrentApprovalRun'] = function ($q) {
            return $q->select([
                'id',
                'recommendation_id',
                'workflow_instance_id',
                'status',
                'current_step_key',
                'current_step_label',
            ]);
        };

        $baseQuery = $recommendationsTable->find()
            ->where(['Recommendations.requester_id' => $memberId])
            ->innerJoinWith('Awards.AwardBranch')
            ->leftJoinWith('Awards.Domains')
            ->leftJoinWith('CurrentApprovalRun')
            ->innerJoinWith('Awards.Levels');
        if ($selectFields !== null) {
            $baseQuery->select($selectFields);
        }
        $baseQuery->contain($contain);

        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'memberSubmitted']);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.memberSubmitted',
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
    public function buildRecsForMemberQuery(
        Table $recommendationsTable,
        int $memberId,
        ?array $visibleColumns = null,
    ): array {
        $includeGatherings = $this->shouldLoadDisplayColumn('gatherings', $visibleColumns);
        $includeAssignedGathering = $this->shouldLoadDisplayColumn('assigned_gathering', $visibleColumns);
        $selectFields = $this->recommendationSelectFields($visibleColumns);

        $contain = [
            'Requesters' => function ($q) {
                return $q->select(['id', 'sca_name']);
            },
            'Awards' => function ($q) {
                return $q->select(['id', 'abbreviation', 'branch_id', 'level_id']);
            },
            'Awards.Levels' => function ($q) {
                return $q->select(['id', 'name']);
            },
            'Awards.AwardBranch' => function ($q) {
                return $q->select(['id', 'name', 'type']);
            },
        ];
        if ($includeGatherings) {
            $contain['Gatherings'] = function ($q) {
                return $q->select(['id', 'name', 'start_date', 'end_date']);
            };
        }
        if ($includeAssignedGathering) {
            $contain['AssignedGathering'] = function ($q) {
                return $q->select(['id', 'name', 'cancelled_at']);
            };
        }
        $contain['CurrentApprovalRun'] = function ($q) {
            return $q->select([
                'id',
                'recommendation_id',
                'workflow_instance_id',
                'status',
                'current_step_key',
                'current_step_label',
            ]);
        };

        $baseQuery = $recommendationsTable->find()
            ->where(['Recommendations.member_id' => $memberId])
            ->innerJoinWith('Awards.AwardBranch')
            ->leftJoinWith('Awards.Domains')
            ->leftJoinWith('CurrentApprovalRun')
            ->innerJoinWith('Awards.Levels');
        if ($selectFields !== null) {
            $baseQuery->select($selectFields);
        }
        $baseQuery->contain($contain);

        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'recsForMember']);

        $gridOptions = [
            'gridKey' => 'Awards.Recommendations.forMember',
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

    /**
     * Determine whether a display-only association should be loaded.
     *
     * @param string $columnKey Grid column key backed by association data.
     * @param array<int,string>|null $visibleColumns Resolved visible columns; null means load all.
     * @return bool
     */
    private function shouldLoadDisplayColumn(string $columnKey, ?array $visibleColumns): bool
    {
        if ($visibleColumns === null) {
            return true;
        }

        return in_array($columnKey, $visibleColumns, true);
    }

    /**
     * Select high-traffic grid fields while skipping large text fields when hidden.
     *
     * @param array<int,string>|null $visibleColumns Resolved visible columns; null means select all.
     * @return array<int,string>|null
     */
    private function recommendationSelectFields(?array $visibleColumns): ?array
    {
        if ($visibleColumns === null) {
            return null;
        }

        $fields = [
            'Recommendations.id',
            'Recommendations.stack_rank',
            'Recommendations.recommendation_group_id',
            'Recommendations.requester_id',
            'Recommendations.member_id',
            'Recommendations.branch_id',
            'Recommendations.award_id',
            'Recommendations.event_id',
            'Recommendations.gathering_id',
            'Recommendations.bestowal_id',
            'Recommendations.requester_sca_name',
            'Recommendations.member_sca_name',
            'Recommendations.contact_number',
            'Recommendations.contact_email',
            'Recommendations.specialty',
            'Recommendations.call_into_court',
            'Recommendations.court_availability',
            'Recommendations.person_to_notify',
            'Recommendations.status',
            'Recommendations.state',
            'Recommendations.state_date',
            'Recommendations.given',
            'Recommendations.created',
            'Recommendations.modified',
            'Recommendations.deleted',
        ];

        if ($this->shouldLoadDisplayColumn('reason', $visibleColumns)) {
            $fields[] = 'Recommendations.reason';
        }
        if ($this->shouldLoadDisplayColumn('close_reason', $visibleColumns)) {
            $fields[] = 'Recommendations.close_reason';
        }

        return $fields;
    }

    /**
     * Return accessible labels for recommendation bulk selection controls.
     *
     * @return array<string, string>
     */
    private function recommendationBulkSelectionConfig(): array
    {
        return [
            'selectAllLabel' => __('Select all recommendations on this page'),
            'rowLabelTemplate' => __('Select row for {member_sca_name} - {award_name} row {id}'),
        ];
    }
}
