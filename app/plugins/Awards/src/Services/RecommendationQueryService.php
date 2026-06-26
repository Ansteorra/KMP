<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Table\WorkflowApprovalsTable;
use Awards\KMP\GridColumns\RecommendationsGridColumns;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\RecommendationApprovalRun;
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
     * @param int|null $currentMemberId Current member ID for member-specific system views.
     * @return array{query: \Cake\ORM\Query\SelectQuery, gridOptions: array} Base query and processDataverseGrid options.
     */
    public function buildMainGridQuery(
        Table $recommendationsTable,
        bool $canEdit,
        bool $includeNotes = true,
        ?array $visibleColumns = null,
        ?int $currentMemberId = null,
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
            'queryCallback' => $this->buildRecommendationSystemViewCallback($currentMemberId),
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
     * Build system-view-specific recommendation grid filters that require conditions
     * beyond the flat saved-view expression format.
     *
     * @return callable(\Cake\ORM\Query\SelectQuery, array<string, mixed>|null): \Cake\ORM\Query\SelectQuery
     */
    private function buildRecommendationSystemViewCallback(?int $currentMemberId = null): callable
    {
        return function (SelectQuery $query, ?array $selectedSystemView) use ($currentMemberId): SelectQuery {
            $viewId = $selectedSystemView['id'] ?? null;

            if ($viewId === 'sys-recs-in-approval') {
                return $query
                    ->where([
                        'CurrentApprovalRun.status IN' => [
                            RecommendationApprovalRun::STATUS_IN_PROGRESS,
                            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                        ],
                    ])
                    ->where([$this->workflowInstanceRejectedResponseMissingSql('CurrentApprovalRun.workflow_instance_id')]);
            }

            if ($viewId === 'sys-recs-needs-my-approval') {
                if ($currentMemberId === null || $currentMemberId <= 0) {
                    return $query->where(['1 = 0']);
                }

                $pendingWorkflowInstanceIds = WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember(
                    $currentMemberId,
                );
                if ($pendingWorkflowInstanceIds === []) {
                    return $query->where(['1 = 0']);
                }

                return $query->where([
                    'CurrentApprovalRun.workflow_instance_id IN' => $pendingWorkflowInstanceIds,
                    'CurrentApprovalRun.status IN' => [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                ]);
            }

            if ($viewId === 'sys-recs-approved-by-me') {
                if ($currentMemberId === null || $currentMemberId <= 0) {
                    return $query->where(['1 = 0']);
                }

                return $query
                    ->where(['CurrentApprovalRun.id IS NOT' => null])
                    ->where([
                        sprintf(
                            "EXISTS (
                                SELECT 1
                                FROM workflow_approvals abm_approvals
                                INNER JOIN workflow_approval_responses abm_responses
                                    ON abm_responses.workflow_approval_id = abm_approvals.id
                                WHERE abm_approvals.workflow_instance_id = CurrentApprovalRun.workflow_instance_id
                                  AND abm_responses.member_id = %d
                                  AND abm_responses.decision = '%s'
                            )",
                            $currentMemberId,
                            WorkflowApprovalResponse::DECISION_APPROVE,
                        ),
                    ]);
            }

            if ($viewId === 'sys-recs-converted') {
                return $query->where([
                    'Recommendations.bestowal_id IS NOT' => null,
                    'Bestowals.lifecycle_status !=' => Bestowal::LIFECYCLE_GIVEN,
                ]);
            }

            if ($viewId === 'sys-recs-archived') {
                $archivedStates = RecommendationsGridColumns::getArchivedStates();

                return $query->where([
                    'OR' => [
                        [
                            'Recommendations.state IN' => $archivedStates,
                            'Recommendations.bestowal_id IS' => null,
                        ],
                        'Bestowals.lifecycle_status' => Bestowal::LIFECYCLE_GIVEN,
                        sprintf(
                            "EXISTS (
                                SELECT 1
                                FROM awards_recommendation_approval_runs archived_runs
                                WHERE archived_runs.recommendation_id = Recommendations.id
                                  AND archived_runs.deleted IS NULL
                                  AND archived_runs.status = '%s'
                                  AND archived_runs.terminal_reason = '%s'
                            )",
                            RecommendationApprovalRun::STATUS_CLOSED,
                            RecommendationApprovalRun::TERMINAL_REASON_REJECTED,
                        ),
                        $this->recommendationRejectedResponseExistsSql(),
                    ],
                ]);
            }

            return $query;
        };
    }

    /**
     * Build SQL that matches any rejected workflow response for a recommendation's approval runs.
     *
     * @return string
     */
    private function recommendationRejectedResponseExistsSql(): string
    {
        return sprintf(
            "EXISTS (
                SELECT 1
                FROM awards_recommendation_approval_runs response_runs
                INNER JOIN workflow_approvals response_approvals
                    ON response_approvals.workflow_instance_id = response_runs.workflow_instance_id
                INNER JOIN workflow_approval_responses response_decisions
                    ON response_decisions.workflow_approval_id = response_approvals.id
                WHERE response_runs.recommendation_id = Recommendations.id
                  AND response_runs.deleted IS NULL
                  AND response_decisions.decision = '%s'
            )",
            WorkflowApprovalResponse::DECISION_REJECT,
        );
    }

    /**
     * Build SQL that excludes workflow instances that have already received a reject response.
     *
     * @param string $workflowInstanceField SQL field/expression for the workflow instance ID.
     * @return string
     */
    private function workflowInstanceRejectedResponseMissingSql(string $workflowInstanceField): string
    {
        return sprintf(
            "NOT EXISTS (
                SELECT 1
                FROM workflow_approvals active_response_approvals
                INNER JOIN workflow_approval_responses active_response_decisions
                    ON active_response_decisions.workflow_approval_id = active_response_approvals.id
                WHERE active_response_approvals.workflow_instance_id = %s
                  AND active_response_decisions.decision = '%s'
            )",
            $workflowInstanceField,
            WorkflowApprovalResponse::DECISION_REJECT,
        );
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
