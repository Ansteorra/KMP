<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Awards\Model\Entity\RecommendationMigrationResult;
use Awards\Model\Entity\RecommendationMigrationRun;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Audits and reconciles legacy Awards recommendations into their new lifecycle owners.
 */
class RecommendationMigrationService
{
    use LocatorAwareTrait;

    public const WORKFLOW_EVENT = 'Awards.ExistingRecommendationApprovalRequested';
    public const WORKFLOW_SLUG = 'awards-existing-recommendation-approval';

    private const CLOSED_STATES = [
        'Given',
        'No Action',
        'Deferred till Later',
        'Linked',
    ];

    private const BESTOWAL_STATES = [
        RecommendationBestowalStatePolicyService::HANDOFF_STATE,
        'Scheduled',
        'Announced Not Given',
        'King Approved',
        'Queen Approved',
    ];

    private const APPROVAL_STATES = [
        'Submitted',
        'In Consideration',
        'Awaiting Feedback',
    ];

    private Table $recommendationsTable;
    private ?Table $migrationRunsTable = null;
    private ?Table $migrationResultsTable = null;
    private Table $approvalRunsTable;
    private ?Table $workflowDefinitionsTable = null;
    private Table $workflowInstancesTable;
    private Table $workflowApprovalsTable;
    private Table $feedbackRequestItemsTable;
    private RecommendationTransitionService $transitionService;
    private BestowalCreationService $bestowalCreationService;
    private AwardApprovalResolverService $approvalResolver;
    private ?TriggerDispatcher $triggerDispatcher = null;

    /**
     * @param \App\Services\WorkflowEngine\TriggerDispatcher|null $triggerDispatcher Optional dispatcher
     * @param \Awards\Services\RecommendationTransitionService|null $transitionService Optional transition service
     * @param \Awards\Services\BestowalCreationService|null $bestowalCreationService Optional bestowal service
     * @param \Awards\Services\AwardApprovalResolverService|null $approvalResolver Optional approval resolver
     */
    public function __construct(
        ?TriggerDispatcher $triggerDispatcher = null,
        ?RecommendationTransitionService $transitionService = null,
        ?BestowalCreationService $bestowalCreationService = null,
        ?AwardApprovalResolverService $approvalResolver = null,
    ) {
        $this->recommendationsTable = $this->fetchTable('Awards.Recommendations');
        $this->approvalRunsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
        $this->workflowInstancesTable = $this->fetchTable('WorkflowInstances');
        $this->workflowApprovalsTable = $this->fetchTable('WorkflowApprovals');
        $this->feedbackRequestItemsTable = $this->fetchTable('Awards.RecommendationFeedbackRequestItems');
        $this->transitionService = $transitionService ?? new RecommendationTransitionService();
        $this->bestowalCreationService = $bestowalCreationService ?? new BestowalCreationService();
        $this->approvalResolver = $approvalResolver ?? new AwardApprovalResolverService();
        $this->triggerDispatcher = $triggerDispatcher;
    }

    /**
     * Run the migration in dry-run, apply, or resume mode.
     *
     * @param string $mode RecommendationMigrationRun::MODE_* value
     * @param array<string, mixed> $filters Optional recommendation filters
     * @param int $actorId Actor member ID for mutation/audit fields
     * @return \App\Services\ServiceResult
     */
    public function run(string $mode, array $filters, int $actorId): ServiceResult
    {
        if (
            !in_array(
                $mode,
                [
                    RecommendationMigrationRun::MODE_DRY_RUN,
                    RecommendationMigrationRun::MODE_APPLY,
                    RecommendationMigrationRun::MODE_RESUME,
                ],
                true,
            )
        ) {
            return new ServiceResult(false, "Unsupported migration mode '{$mode}'.");
        }

        $preflight = $this->preflight($filters);
        if (!$preflight->isSuccess()) {
            return $preflight;
        }

        $migrationRunsTable = $this->getMigrationRunsTable();
        $run = $migrationRunsTable->newEntity([
            'mode' => $mode,
            'status' => RecommendationMigrationRun::STATUS_RUNNING,
            'filters' => $filters,
            'started' => DateTime::now(),
        ]);
        $migrationRunsTable->saveOrFail($run);

        $summary = [
            'closed' => 0,
            'bestowal' => 0,
            'approval_workflow' => 0,
            'manual_review' => 0,
            'skipped' => 0,
            'error' => 0,
        ];

        try {
            $recommendations = $this->buildRecommendationQuery($filters)->all();
            foreach ($recommendations as $recommendation) {
                $classification = $this->classify($recommendation);
                $result = $this->applyClassification($run->id, $recommendation, $classification, $mode, $actorId);
                $target = (string)$result->target_action;
                $status = (string)$result->result_status;
                if (isset($summary[$target])) {
                    $summary[$target]++;
                }
                if ($status === RecommendationMigrationResult::STATUS_ERROR) {
                    $summary['error']++;
                } elseif ($status === RecommendationMigrationResult::STATUS_SKIPPED) {
                    $summary['skipped']++;
                }
            }

            if ($mode !== RecommendationMigrationRun::MODE_DRY_RUN) {
                $postMigrationAudit = $this->auditOpenRecommendationsWithoutWorkflow($filters);
                if ($postMigrationAudit['count'] > 0) {
                    throw new RuntimeException($this->formatOpenRecommendationAuditFailure($postMigrationAudit));
                }
            }

            $run->status = RecommendationMigrationRun::STATUS_COMPLETED;
            $run->completed = DateTime::now();
            $run->summary = $summary;
            $migrationRunsTable->saveOrFail($run);

            return new ServiceResult(true, null, [
                'runId' => (int)$run->id,
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            $run->status = RecommendationMigrationRun::STATUS_FAILED;
            $run->completed = DateTime::now();
            $run->summary = $summary + ['errorMessage' => $e->getMessage()];
            $migrationRunsTable->saveOrFail($run);

            return new ServiceResult(false, $e->getMessage(), [
                'runId' => (int)$run->id,
                'summary' => $summary,
            ]);
        }
    }

    /**
     * Validate prerequisites without mutating records.
     */
    public function preflight(array $filters = []): ServiceResult
    {
        $groupedCount = $this->applyRecommendationFilters($this->recommendationsTable->find(), $filters)
            ->where(['recommendation_group_id IS NOT' => null])
            ->count();
        if ($groupedCount > 0) {
            return new ServiceResult(
                false,
                "Grouped recommendations were found ({$groupedCount}); migration preflight failed.",
            );
        }

        $workflow = $this->getWorkflowDefinitionsTable()->find()
            ->where([
                'slug' => self::WORKFLOW_SLUG,
                'is_active' => true,
                'current_version_id IS NOT' => null,
                'deleted IS' => null,
            ])
            ->first();
        if (!$workflow) {
            return new ServiceResult(false, 'Existing recommendation approval workflow is not active and published.');
        }

        $missingApprovalProcessCount = $this->applyRecommendationFilters(
            $this->recommendationsTable->find()->innerJoinWith('Awards'),
            $filters,
        )
            ->where([
                'Recommendations.state IN' => self::APPROVAL_STATES,
                'Recommendations.bestowal_id IS' => null,
                'Awards.approval_process_id IS' => null,
            ])
            ->count();
        if ($missingApprovalProcessCount > 0) {
            return new ServiceResult(
                false,
                "{$missingApprovalProcessCount} approval-owned recommendations have awards without approval processes.",
            );
        }

        return new ServiceResult(true);
    }

    /**
     * Find open recommendations that still lack workflow or bestowal ownership.
     *
     * @param array<string, mixed> $filters Optional recommendation filters
     * @return array{count:int,recommendations:array<int, array{id:int,state:string|null,status:string|null}>}
     */
    public function auditOpenRecommendationsWithoutWorkflow(array $filters = []): array
    {
        $query = $this->applyRecommendationFilters($this->openRecommendationsWithoutWorkflowQuery(), $filters);
        $count = $query->count();
        $sampleRows = $query
            ->select(['Recommendations.id', 'Recommendations.state', 'Recommendations.status'])
            ->orderBy(['Recommendations.id' => 'ASC'])
            ->limit(25)
            ->enableHydration(false)
            ->all()
            ->toList();

        $recommendations = [];
        foreach ($sampleRows as $row) {
            $recommendations[] = [
                'id' => (int)$row['id'],
                'state' => $row['state'] === null ? null : (string)$row['state'],
                'status' => $row['status'] === null ? null : (string)$row['status'],
            ];
        }

        return [
            'count' => $count,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to classify
     * @return array{target:string,reason:string}
     */
    public function classify(Recommendation $recommendation): array
    {
        $state = (string)$recommendation->state;
        if ($recommendation->recommendation_group_id !== null) {
            return [
                'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                'reason' => 'Grouped recommendations are outside the approved migration scope.',
            ];
        }

        if ($this->hasActiveFeedbackRequest((int)$recommendation->id)) {
            return [
                'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                'reason' => 'Recommendation has an active feedback request.',
            ];
        }

        if ($recommendation->award_id === null || $recommendation->member_id === null) {
            return [
                'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                'reason' => 'Recommendation is missing award or member data required for migration.',
            ];
        }

        if ($recommendation->bestowal_id !== null) {
            return [
                'target' => RecommendationMigrationResult::TARGET_BESTOWAL,
                'reason' => 'Recommendation already has a bestowal link.',
            ];
        }

        if (in_array($state, self::CLOSED_STATES, true)) {
            return [
                'target' => RecommendationMigrationResult::TARGET_CLOSED,
                'reason' => "State '{$state}' is closeable.",
            ];
        }

        if (in_array($state, self::BESTOWAL_STATES, true)) {
            return [
                'target' => RecommendationMigrationResult::TARGET_BESTOWAL,
                'reason' => "State '{$state}' is bestowal-owned or ready for bestowal handoff.",
            ];
        }

        if (in_array($state, self::APPROVAL_STATES, true)) {
            $approvalReadinessIssue = $this->approvalReadinessIssue($recommendation);
            if ($approvalReadinessIssue !== null) {
                return [
                    'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                    'reason' => $approvalReadinessIssue,
                ];
            }

            return [
                'target' => RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                'reason' => "State '{$state}' still needs approval workflow ownership.",
            ];
        }

        return [
            'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            'reason' => "State '{$state}' is not recognized by the migration classifier.",
        ];
    }

    /**
     * Build the scoped recommendation query for a migration run.
     *
     * @param array<string, mixed> $filters Optional filters
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function buildRecommendationQuery(array $filters): SelectQuery
    {
        $query = $this->recommendationsTable->find()
            ->contain(['Awards.ApprovalProcesses.ApprovalProcessSteps'])
            ->orderBy(['Recommendations.id' => 'ASC']);

        return $this->applyRecommendationFilters($query, $filters);
    }

    /**
     * Apply migration recommendation filters to a query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Query to filter
     * @param array<string, mixed> $filters Optional filters
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function applyRecommendationFilters(SelectQuery $query, array $filters): SelectQuery
    {
        if (!empty($filters['recommendation_id'])) {
            $query->where(['Recommendations.id' => (int)$filters['recommendation_id']]);
        }
        if (!empty($filters['award_id'])) {
            $query->where(['Recommendations.award_id' => (int)$filters['award_id']]);
        }
        if (!empty($filters['branch_id'])) {
            $query->where(['Recommendations.branch_id' => (int)$filters['branch_id']]);
        }
        if (!empty($filters['state'])) {
            $query->where(['Recommendations.state' => (string)$filters['state']]);
        }

        return $query;
    }

    /**
     * Build the open recommendation audit query.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function openRecommendationsWithoutWorkflowQuery(): SelectQuery
    {
        $workflowRecommendationIds = $this->workflowInstancesTable->find()
            ->select(['WorkflowInstances.entity_id'])
            ->innerJoinWith('WorkflowDefinitions')
            ->where([
                'WorkflowDefinitions.slug' => self::WORKFLOW_SLUG,
                'WorkflowInstances.entity_type' => 'Awards.Recommendations',
                'WorkflowInstances.entity_id IS NOT' => null,
                'WorkflowInstances.status IN' => [
                    WorkflowInstance::STATUS_PENDING,
                    WorkflowInstance::STATUS_RUNNING,
                    WorkflowInstance::STATUS_WAITING,
                ],
            ]);

        return $this->recommendationsTable->find()
            ->where([
                'Recommendations.status !=' => 'Closed',
                'Recommendations.bestowal_id IS' => null,
                'Recommendations.deleted IS' => null,
                'Recommendations.id NOT IN' => $workflowRecommendationIds,
            ]);
    }

    /**
     * Format an audit failure for console/service output.
     *
     * @param array{count:int,recommendations:array<int, array{id:int,state:string|null,status:string|null}>} $audit Audit result
     * @return string
     */
    private function formatOpenRecommendationAuditFailure(array $audit): string
    {
        $sample = [];
        foreach ($audit['recommendations'] as $recommendation) {
            $sample[] = sprintf(
                '#%d (%s / %s)',
                $recommendation['id'],
                $recommendation['status'] ?? 'unknown status',
                $recommendation['state'] ?? 'unknown state',
            );
        }

        $suffix = $sample === [] ? '' : ' Sample: ' . implode(', ', $sample) . '.';

        return sprintf(
            '%d open recommendations still lack workflow or bestowal ownership after migration.%s',
            $audit['count'],
            $suffix,
        );
    }

    /**
     * Check whether an approval-owned recommendation can reach its first approval gate.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation
     * @return string|null Manual-review reason, or null when ready
     */
    private function approvalReadinessIssue(Recommendation $recommendation): ?string
    {
        if ($recommendation->award_id === null) {
            return 'Recommendation is missing award data required for approval workflow migration.';
        }

        $award = $recommendation->award ?? null;
        if ($award === null || !isset($award->approval_process)) {
            $award = $this->fetchTable('Awards.Awards')->get((int)$recommendation->award_id, contain: [
                'ApprovalProcesses.ApprovalProcessSteps',
            ]);
        }
        $process = $award->approval_process ?? null;
        if ($process === null || !$process->is_active) {
            return 'Recommendation award does not have an active approval process.';
        }

        $steps = $process->approval_process_steps ?? [];
        if ($steps === []) {
            return 'Recommendation award approval process does not have any approval steps.';
        }

        $firstStep = array_values($steps)[0];
        try {
            $approvers = $this->approvalResolver->resolveApprovers($firstStep, $award);
        } catch (RuntimeException $exception) {
            return 'Recommendation approval process cannot resolve approvers: ' . $exception->getMessage();
        }

        if ($approvers === []) {
            return sprintf(
                'Recommendation approval process step "%s" has no eligible approvers.',
                (string)($firstStep->label ?? $firstStep->step_key ?? $firstStep->id),
            );
        }

        return null;
    }

    /**
     * Apply one classification and persist an audit result.
     *
     * @param string|int $runId Migration run ID
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation
     * @param array{target:string,reason:string} $classification Classification data
     * @param string $mode Run mode
     * @param int $actorId Actor member ID
     * @return \Cake\Datasource\EntityInterface
     */
    private function applyClassification(
        int|string $runId,
        Recommendation $recommendation,
        array $classification,
        string $mode,
        int $actorId,
    ): EntityInterface {
        $resultData = [
            'migration_run_id' => (int)$runId,
            'recommendation_id' => (int)$recommendation->id,
            'original_state' => $recommendation->state,
            'original_status' => $recommendation->status,
            'target_action' => $classification['target'],
            'result_status' => $mode === RecommendationMigrationRun::MODE_DRY_RUN
                ? RecommendationMigrationResult::STATUS_PLANNED
                : RecommendationMigrationResult::STATUS_APPLIED,
            'reason' => $classification['reason'],
            'details' => [],
        ];

        if ($mode === RecommendationMigrationRun::MODE_DRY_RUN) {
            return $this->saveResult($resultData);
        }

        try {
            if ($classification['target'] === RecommendationMigrationResult::TARGET_CLOSED) {
                $resultData = $this->applyClosed($recommendation, $resultData, $actorId);
            } elseif ($classification['target'] === RecommendationMigrationResult::TARGET_BESTOWAL) {
                $resultData = $this->applyBestowal($recommendation, $resultData, $actorId);
            } elseif ($classification['target'] === RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW) {
                $resultData = $this->applyApprovalWorkflow($recommendation, $resultData, $actorId);
            } elseif ($classification['target'] === RecommendationMigrationResult::TARGET_MANUAL_REVIEW) {
                $resultData['result_status'] = RecommendationMigrationResult::STATUS_SKIPPED;
            }
        } catch (Throwable $e) {
            $resultData['result_status'] = RecommendationMigrationResult::STATUS_ERROR;
            $resultData['reason'] = $e->getMessage();
        }

        return $this->saveResult($resultData);
    }

    /**
     * @param array<string, mixed> $resultData Result data
     * @return \Cake\Datasource\EntityInterface
     */
    private function saveResult(array $resultData): EntityInterface
    {
        $migrationResultsTable = $this->getMigrationResultsTable();
        $result = $migrationResultsTable->newEntity($resultData);

        return $migrationResultsTable->saveOrFail($result);
    }

    /**
     * @param array<string, mixed> $resultData Result data
     * @return array<string, mixed>
     */
    private function applyClosed(Recommendation $recommendation, array $resultData, int $actorId): array
    {
        $this->closeActiveApprovalRuns((int)$recommendation->id, $actorId);
        if ((string)$recommendation->status === 'Closed') {
            return $resultData;
        }

        $targetState = (string)$recommendation->state === 'Linked'
            && in_array('Linked - Closed', Recommendation::getStates(), true)
                ? 'Linked - Closed'
                : RecommendationBestowalStatePolicyService::NO_ACTION_STATE;
        $transition = $this->transitionService->transition(
            $this->recommendationsTable,
            (int)$recommendation->id,
            [
                'state' => $targetState,
                'close_reason' => 'Closed by recommendation migration.',
                'note' => 'Recommendation closed by recommendation migration.',
            ],
            $actorId,
        );
        if (!($transition['success'] ?? false)) {
            throw new RuntimeException((string)($transition['error'] ?? 'Failed to close recommendation.'));
        }

        return $resultData;
    }

    /**
     * @param array<string, mixed> $resultData Result data
     * @return array<string, mixed>
     */
    private function applyBestowal(Recommendation $recommendation, array $resultData, int $actorId): array
    {
        if ($recommendation->bestowal_id !== null) {
            $resultData['bestowal_id'] = (int)$recommendation->bestowal_id;

            return $resultData;
        }

        $bestowalResult = $this->bestowalCreationService->createFromRecommendation((int)$recommendation->id, $actorId);
        if (!($bestowalResult['success'] ?? false)) {
            throw new RuntimeException((string)($bestowalResult['error'] ?? 'Failed to create bestowal.'));
        }
        if (!empty($bestowalResult['data']['bestowalId'])) {
            $resultData['bestowal_id'] = (int)$bestowalResult['data']['bestowalId'];
        }

        return $resultData;
    }

    /**
     * @param array<string, mixed> $resultData Result data
     * @return array<string, mixed>
     */
    private function applyApprovalWorkflow(Recommendation $recommendation, array $resultData, int $actorId): array
    {
        $existingRun = $this->findActiveApprovalRun((int)$recommendation->id);
        if ($existingRun !== null) {
            $this->assertPendingApprovalWorkflow($existingRun, $actorId);
            $resultData['approval_run_id'] = (int)$existingRun->id;
            $resultData['workflow_instance_id'] = (int)$existingRun->workflow_instance_id;
            $resultData['result_status'] = RecommendationMigrationResult::STATUS_SKIPPED;
            $resultData['reason'] = 'Recommendation already has an active approval run.';

            return $resultData;
        }

        $existingInstance = $this->findActiveWorkflowInstance((int)$recommendation->id);
        if ($existingInstance !== null) {
            $resultData['workflow_instance_id'] = (int)$existingInstance->id;
            $resultData['result_status'] = RecommendationMigrationResult::STATUS_SKIPPED;
            $resultData['reason'] = 'Recommendation already has an active workflow instance.';

            return $resultData;
        }

        $results = $this->getTriggerDispatcher()->dispatch(
            self::WORKFLOW_EVENT,
            [
                'recommendationId' => (int)$recommendation->id,
                'actorId' => $actorId,
                'migration' => true,
            ],
            $actorId,
        );
        $started = null;
        foreach ($results as $dispatchResult) {
            if ($dispatchResult instanceof ServiceResult && $dispatchResult->isSuccess()) {
                $started = $dispatchResult->getData();
                break;
            }
        }
        if ($started === null) {
            throw new RuntimeException('Existing recommendation approval workflow did not start.');
        }

        if (!empty($started['instanceId'])) {
            $resultData['workflow_instance_id'] = (int)$started['instanceId'];
            $run = $this->findActiveApprovalRun((int)$recommendation->id);
            if ($run !== null) {
                $this->assertPendingApprovalWorkflow($run, $actorId);
                $resultData['approval_run_id'] = (int)$run->id;
            }
        }

        return $resultData;
    }

    /**
     * Confirm approval workflow ownership reached a pending approval gate.
     *
     * @param \Cake\Datasource\EntityInterface $run Active approval run
     * @param int $actorId Actor member ID
     * @return void
     */
    private function assertPendingApprovalWorkflow(EntityInterface $run, int $actorId): void
    {
        $workflowInstanceId = (int)$run->workflow_instance_id;
        $instance = $this->workflowInstancesTable->get($workflowInstanceId);
        $pendingApprovalCount = $this->workflowApprovalsTable->find()
            ->where([
                'workflow_instance_id' => $workflowInstanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->count();

        if ((string)$instance->status === WorkflowInstance::STATUS_WAITING && $pendingApprovalCount > 0) {
            return;
        }

        $run->status = RecommendationApprovalRun::STATUS_CLOSED;
        $run->completed = DateTime::now();
        $run->modified_by = $actorId;
        $this->approvalRunsTable->saveOrFail($run);

        $reason = $this->failedWorkflowReason($instance);
        throw new RuntimeException(sprintf(
            'Existing recommendation approval workflow did not create a pending approval gate%s.',
            $reason === '' ? '' : ': ' . $reason,
        ));
    }

    /**
     * Extract a workflow failure reason from the instance context when available.
     *
     * @param \Cake\Datasource\EntityInterface $instance Workflow instance
     * @return string
     */
    private function failedWorkflowReason(EntityInterface $instance): string
    {
        $context = $instance->context;
        if (!is_array($context)) {
            return '';
        }

        $startResult = $context['nodes']['start-approval-process']['result'] ?? null;
        if (is_array($startResult) && !empty($startResult['error'])) {
            return (string)$startResult['error'];
        }

        $workflowResult = $context['workflowResult'] ?? null;
        if (is_array($workflowResult) && !empty($workflowResult['error'])) {
            return (string)$workflowResult['error'];
        }

        return '';
    }

    /**
     * Determine whether the recommendation still has an open feedback request.
     *
     * @param int $recommendationId Recommendation ID
     * @return bool
     */
    private function hasActiveFeedbackRequest(int $recommendationId): bool
    {
        return $this->feedbackRequestItemsTable->find()
            ->innerJoinWith('FeedbackRequests')
            ->where([
                'RecommendationFeedbackRequestItems.recommendation_id' => $recommendationId,
                'FeedbackRequests.status' => RecommendationFeedbackRequest::STATUS_PENDING,
            ])
            ->count() > 0;
    }

    /**
     * Find an active recommendation approval run.
     *
     * @param int $recommendationId Recommendation ID
     * @return \Cake\Datasource\EntityInterface|null
     */
    private function findActiveApprovalRun(int $recommendationId): ?EntityInterface
    {
        return $this->approvalRunsTable->find()
            ->where([
                'recommendation_id' => $recommendationId,
                'status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->orderBy(['id' => 'DESC'])
            ->first();
    }

    /**
     * Find an active existing-recommendation workflow instance.
     *
     * @param int $recommendationId Recommendation ID
     * @return \Cake\Datasource\EntityInterface|null
     */
    private function findActiveWorkflowInstance(int $recommendationId): ?EntityInterface
    {
        return $this->workflowInstancesTable->find()
            ->innerJoinWith('WorkflowDefinitions')
            ->where([
                'WorkflowDefinitions.slug' => self::WORKFLOW_SLUG,
                'WorkflowInstances.entity_type' => 'Awards.Recommendations',
                'WorkflowInstances.entity_id' => $recommendationId,
                'WorkflowInstances.status IN' => [
                    WorkflowInstance::STATUS_PENDING,
                    WorkflowInstance::STATUS_RUNNING,
                    WorkflowInstance::STATUS_WAITING,
                ],
            ])
            ->orderBy(['WorkflowInstances.id' => 'DESC'])
            ->first();
    }

    /**
     * Mark active approval runs closed when the recommendation enters the closed path.
     *
     * @param int $recommendationId Recommendation ID
     * @param int $actorId Actor member ID
     * @return void
     */
    private function closeActiveApprovalRuns(int $recommendationId, int $actorId): void
    {
        $runs = $this->approvalRunsTable->find()
            ->where([
                'recommendation_id' => $recommendationId,
                'status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ]);
        foreach ($runs as $run) {
            $run->status = RecommendationApprovalRun::STATUS_CLOSED;
            $run->completed = DateTime::now();
            $run->modified_by = $actorId;
            $this->approvalRunsTable->saveOrFail($run);
        }
    }

    /**
     * Lazily load migration runs table so classifier-only tests do not require audit schema.
     *
     * @return \Cake\ORM\Table
     */
    private function getMigrationRunsTable(): Table
    {
        if ($this->migrationRunsTable === null) {
            $this->migrationRunsTable = $this->fetchTable('Awards.RecommendationMigrationRuns');
        }

        return $this->migrationRunsTable;
    }

    /**
     * Lazily load migration results table so classifier-only tests do not require audit schema.
     *
     * @return \Cake\ORM\Table
     */
    private function getMigrationResultsTable(): Table
    {
        if ($this->migrationResultsTable === null) {
            $this->migrationResultsTable = $this->fetchTable('Awards.RecommendationMigrationResults');
        }

        return $this->migrationResultsTable;
    }

    /**
     * Lazily load workflow definitions table for preflight checks.
     *
     * @return \Cake\ORM\Table
     */
    private function getWorkflowDefinitionsTable(): Table
    {
        if ($this->workflowDefinitionsTable === null) {
            $this->workflowDefinitionsTable = $this->fetchTable('WorkflowDefinitions');
        }

        return $this->workflowDefinitionsTable;
    }

    /**
     * Lazily build the workflow trigger dispatcher.
     *
     * @return \App\Services\WorkflowEngine\TriggerDispatcher
     */
    private function getTriggerDispatcher(): TriggerDispatcher
    {
        if ($this->triggerDispatcher === null) {
            throw new RuntimeException(
                'Recommendation approval workflow migration requires a workflow trigger dispatcher.',
            );
        }

        return $this->triggerDispatcher;
    }
}
