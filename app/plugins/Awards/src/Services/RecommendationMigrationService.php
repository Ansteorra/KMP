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
use Cake\Log\Log;
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

    private const APPROVAL_START_FAILURE_REASON =
        'Recommendation approval workflow could not be started. Review server logs for details.';

    private const APPROVAL_NO_ELIGIBLE_APPROVERS_REASON =
        'Recommendation approval process has no eligible approvers.';

    private const APPROVAL_GATE_MISSING_REASON =
        'Recommendation approval workflow did not create a pending approval gate.';

    private const APPROVAL_OWNERSHIP_AMBIGUOUS_REASON =
        'Recommendation approval workflow ownership is ambiguous and requires manual review.';

    private const MIGRATION_FAILURE_REASON =
        'Recommendation migration failed. Review server logs for details.';

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
     * @param bool $allowOpenManualReview Allow unresolved manual-review recommendations to remain open
     * @return \App\Services\ServiceResult
     */
    public function run(string $mode, array $filters, int $actorId, bool $allowOpenManualReview = false): ServiceResult
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
        $classificationReport = [];
        $classificationRows = [];

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
                $legacyKey = sprintf(
                    '%s / %s',
                    (string)($recommendation->status ?? ''),
                    (string)($recommendation->state ?? ''),
                );
                $reportKey = "{$target}:{$status}";
                $classificationReport[$legacyKey][$reportKey] =
                    ($classificationReport[$legacyKey][$reportKey] ?? 0) + 1;
                if ($mode === RecommendationMigrationRun::MODE_DRY_RUN) {
                    $classificationRows[] = [
                        'recommendationId' => (int)$recommendation->id,
                        'status' => (string)($recommendation->status ?? ''),
                        'state' => (string)($recommendation->state ?? ''),
                        'classification' => $target,
                        'result' => $status,
                        'reason' => (string)($result->reason ?? ''),
                    ];
                }
            }

            if ($mode !== RecommendationMigrationRun::MODE_DRY_RUN && !$allowOpenManualReview) {
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
                'classificationReport' => $classificationReport,
                'records' => $classificationRows,
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
     * Start approval workflows for open approval-owned recommendations that do not have an active run.
     *
     * This is intentionally narrower than the general legacy migration: it never closes a
     * recommendation or creates a bestowal. Each candidate is locked and rechecked in its
     * own transaction so one bad record cannot roll back successful backfills.
     *
     * @param int $actorId Member initiating the backfill
     * @return \App\Services\ServiceResult
     */
    public function backfillOpenApprovalRecommendations(int $actorId): ServiceResult
    {
        $candidateIds = $this->openApprovalRecommendationsWithoutRunQuery()
            ->select(['Recommendations.id'])
            ->orderBy(['Recommendations.id' => 'ASC'])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();
        $summary = [
            'candidateCount' => count($candidateIds),
            'startedCount' => 0,
            'unchangedCount' => 0,
            'skippedCount' => 0,
            'failedCount' => 0,
            'failures' => [],
            'skips' => [],
        ];

        foreach ($candidateIds as $recommendationId) {
            try {
                $outcome = $this->backfillOpenApprovalRecommendation($recommendationId, $actorId);
                $status = (string)($outcome['status'] ?? 'unchanged');
                if ($status === 'started') {
                    $summary['startedCount']++;
                } elseif ($status === 'skipped') {
                    $summary['skippedCount']++;
                    $summary['skips'][] = [
                        'recommendationId' => $recommendationId,
                        'reason' => (string)($outcome['reason'] ?? 'Recommendation requires manual review.'),
                    ];
                } else {
                    $summary['unchangedCount']++;
                }
            } catch (Throwable $exception) {
                if ($this->isManualReviewableApprovalWorkflowFailure($exception)) {
                    $summary['skippedCount']++;
                    $summary['skips'][] = [
                        'recommendationId' => $recommendationId,
                        'reason' => $this->manualReviewableApprovalWorkflowFailureReason($exception),
                    ];

                    continue;
                }

                $summary['failedCount']++;
                $summary['failures'][] = [
                    'recommendationId' => $recommendationId,
                    'reason' => self::APPROVAL_START_FAILURE_REASON,
                ];
                Log::error(sprintf(
                    'Open award recommendation approval backfill failed for recommendation %d: %s',
                    $recommendationId,
                    $exception->getMessage(),
                ));
            }
        }

        $success = $summary['failedCount'] === 0;

        return new ServiceResult(
            $success,
            $success ? null : sprintf(
                '%d open recommendation approval workflow(s) could not be started.',
                $summary['failedCount'],
            ),
            $summary,
        );
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

        if ($recommendation->award_id === null) {
            return [
                'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                'reason' => 'Recommendation is missing award data required for migration.',
            ];
        }

        if ($recommendation->member_id === null && trim((string)$recommendation->member_sca_name) === '') {
            return [
                'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                'reason' => 'Recommendation is missing recipient name required for migration.',
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
            $bestowalReadinessIssue = $this->bestowalReadinessIssue($recommendation);
            if ($bestowalReadinessIssue !== null) {
                return [
                    'target' => RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                    'reason' => $bestowalReadinessIssue,
                ];
            }

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
     * Find the exact population eligible for approval workflow backfill.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function openApprovalRecommendationsWithoutRunQuery(): SelectQuery
    {
        $activeApprovalRecommendationIds = $this->approvalRunsTable->find()
            ->select(['RecommendationApprovalRuns.recommendation_id'])
            ->where([
                'RecommendationApprovalRuns.status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ]);

        return $this->recommendationsTable->find()
            ->where([
                'Recommendations.status !=' => 'Closed',
                'Recommendations.state IN' => self::APPROVAL_STATES,
                'Recommendations.bestowal_id IS' => null,
                'Recommendations.recommendation_group_id IS' => null,
                'Recommendations.deleted IS' => null,
                'Recommendations.id NOT IN' => $activeApprovalRecommendationIds,
            ]);
    }

    /**
     * Lock, recheck, and backfill one approval-owned recommendation.
     *
     * @param int $recommendationId Recommendation selected by the bulk scan
     * @param int $actorId Member initiating the backfill
     * @return array{status:string,reason?:string}
     */
    private function backfillOpenApprovalRecommendation(int $recommendationId, int $actorId): array
    {
        $connection = $this->recommendationsTable->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        try {
            return $connection->transactional(function () use ($recommendationId, $actorId): array {
                $recommendation = $this->recommendationsTable->find()
                    ->where([
                        'Recommendations.id' => $recommendationId,
                        'Recommendations.status !=' => 'Closed',
                        'Recommendations.state IN' => self::APPROVAL_STATES,
                        'Recommendations.bestowal_id IS' => null,
                        'Recommendations.recommendation_group_id IS' => null,
                        'Recommendations.deleted IS' => null,
                    ])
                    ->epilog('FOR UPDATE')
                    ->first();
                if (!$recommendation instanceof Recommendation) {
                    return [
                        'status' => 'unchanged',
                        'reason' => 'Recommendation no longer belongs to the approval backfill population.',
                    ];
                }

                if ($this->findActiveApprovalRun($recommendationId) !== null) {
                    return [
                        'status' => 'unchanged',
                        'reason' => 'Recommendation already has an active approval run.',
                    ];
                }

                $this->recommendationsTable->loadInto($recommendation, [
                    'Awards.ApprovalProcesses.ApprovalProcessSteps',
                ]);
                $classification = $this->classify($recommendation);
                if ($classification['target'] !== RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW) {
                    return [
                        'status' => 'skipped',
                        'reason' => $classification['reason'],
                    ];
                }

                $resultData = $this->applyApprovalWorkflow(
                    $recommendation,
                    [
                        'result_status' => RecommendationMigrationResult::STATUS_APPLIED,
                        'reason' => $classification['reason'],
                    ],
                    $actorId,
                );
                $activeRun = $this->findActiveApprovalRun($recommendationId);
                if ($activeRun !== null) {
                    if (($resultData['result_status'] ?? null) === RecommendationMigrationResult::STATUS_SKIPPED) {
                        return [
                            'status' => 'unchanged',
                            'reason' => (string)($resultData['reason'] ?? 'Recommendation already has an active run.'),
                        ];
                    }

                    return ['status' => 'started'];
                }

                if (($resultData['result_status'] ?? null) === RecommendationMigrationResult::STATUS_SKIPPED) {
                    return [
                        'status' => 'skipped',
                        'reason' => (string)($resultData['reason'] ?? 'Recommendation requires manual review.'),
                    ];
                }

                throw new RecommendationApprovalManualReviewException(
                    RecommendationApprovalManualReviewException::REASON_GATE_MISSING,
                    'Existing recommendation approval workflow did not create a pending approval gate or active run.',
                );
            });
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
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
            Log::error(sprintf(
                'Recommendation %d approval readiness could not resolve approvers: %s',
                (int)$recommendation->id,
                $exception->getMessage(),
            ));

            return 'Recommendation approval process cannot resolve eligible approvers. Review server logs for details.';
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
     * Check whether a bestowal-owned recommendation can safely create or link a bestowal.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation
     * @return string|null Manual-review reason, or null when ready
     */
    private function bestowalReadinessIssue(Recommendation $recommendation): ?string
    {
        $gatheringId = $recommendation->gathering_id;
        if ($gatheringId === null || (int)$gatheringId === 0) {
            return null;
        }

        $gatheringsTable = $this->fetchTable('Gatherings');
        if (!$gatheringsTable->exists(['id' => (int)$gatheringId])) {
            return sprintf(
                'Recommendation references missing gathering #%d; manual review is required before bestowal migration.',
                (int)$gatheringId,
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
            Log::error(sprintf(
                'Recommendation migration failed for recommendation %d: %s',
                (int)$recommendation->id,
                $e->getMessage(),
            ));
            if (
                $classification['target'] === RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW
                && $this->isManualReviewableApprovalWorkflowFailure($e)
            ) {
                $failureReason = $this->manualReviewableApprovalWorkflowFailureReason($e);
                $resultData['target_action'] = RecommendationMigrationResult::TARGET_MANUAL_REVIEW;
                $resultData['result_status'] = RecommendationMigrationResult::STATUS_SKIPPED;
                $resultData['reason'] = 'Approval workflow could not start during migration: ' . $failureReason;
                $resultData['details'] = [
                    'originalTarget' => RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                    'workflowErrorCategory' => $failureReason,
                ];
            } else {
                $resultData['result_status'] = RecommendationMigrationResult::STATUS_ERROR;
                $resultData['reason'] = self::MIGRATION_FAILURE_REASON;
            }
        }

        return $this->saveResult($resultData);
    }

    /**
     * Return true when an approval workflow failure should leave the recommendation for manual review.
     */
    private function isManualReviewableApprovalWorkflowFailure(Throwable $exception): bool
    {
        return $exception instanceof RecommendationApprovalManualReviewException;
    }

    /**
     * Map a known approval-start failure to a safe operator-facing category.
     */
    private function manualReviewableApprovalWorkflowFailureReason(Throwable $exception): string
    {
        if (!$exception instanceof RecommendationApprovalManualReviewException) {
            return self::APPROVAL_START_FAILURE_REASON;
        }

        return match ($exception->getReason()) {
            RecommendationApprovalManualReviewException::REASON_NO_ELIGIBLE_APPROVERS =>
                self::APPROVAL_NO_ELIGIBLE_APPROVERS_REASON,
            RecommendationApprovalManualReviewException::REASON_GATE_MISSING =>
                self::APPROVAL_GATE_MISSING_REASON,
            RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS =>
                self::APPROVAL_OWNERSHIP_AMBIGUOUS_REASON,
            default => self::APPROVAL_START_FAILURE_REASON,
        };
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
            $run = $this->repairActiveWorkflowOwnership($recommendation, $existingInstance, $actorId);
            $resultData['workflow_instance_id'] = (int)$existingInstance->id;
            $resultData['approval_run_id'] = (int)$run->id;
            $resultData['reason'] = 'Active recommendation approval workflow ownership was repaired.';
            $resultData['details'] = [
                'ownershipRepaired' => true,
            ];

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
        $failureReasons = [];
        foreach ($results as $dispatchResult) {
            if ($dispatchResult instanceof ServiceResult && $dispatchResult->isSuccess()) {
                $started = $dispatchResult->getData();
                break;
            }
            if ($dispatchResult instanceof ServiceResult && $dispatchResult->getError() !== null) {
                $failureReasons[] = (string)$dispatchResult->getError();
            }
        }
        if ($started === null) {
            $failureReasons = array_values(array_unique(array_filter($failureReasons)));
            if ($failureReasons !== []) {
                throw new RuntimeException(implode('; ', $failureReasons));
            }

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
        throw new RecommendationApprovalManualReviewException(
            RecommendationApprovalManualReviewException::REASON_GATE_MISSING,
            sprintf(
                'Existing recommendation approval workflow did not create a pending approval gate%s.',
                $reason === '' ? '' : ': ' . $reason,
            ),
        );
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
        $instances = $this->workflowInstancesTable->find()
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
            ->all()
            ->toList();
        if (count($instances) > 1) {
            throw new RecommendationApprovalManualReviewException(
                RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                sprintf(
                    'Recommendation %d approval workflow ownership cannot be safely repaired: '
                    . 'found %d active workflow instances.',
                    $recommendationId,
                    count($instances),
                ),
            );
        }

        return $instances[0] ?? null;
    }

    /**
     * Recreate a missing approval-run projection for one active workflow instance.
     *
     * The workflow instance, approval gates, and responses are the durable evidence.
     * Repair only relinks their run projection; it never replaces a gate or response.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being repaired
     * @param \Cake\Datasource\EntityInterface $instance Active existing-recommendation workflow
     * @param int $actorId Member initiating the repair
     * @return \Cake\Datasource\EntityInterface
     */
    private function repairActiveWorkflowOwnership(
        Recommendation $recommendation,
        EntityInterface $instance,
        int $actorId,
    ): EntityInterface {
        $recommendationId = (int)$recommendation->id;
        $instanceId = (int)$instance->id;
        $existingRun = $this->findActiveApprovalRun($recommendationId);
        if ($existingRun !== null) {
            return $existingRun;
        }

        $approvals = $this->workflowApprovalsTable->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'node_id' => 'award-approval-gate',
            ])
            ->orderBy(['id' => 'ASC'])
            ->epilog('FOR UPDATE')
            ->all()
            ->toList();
        $ownedApprovals = [];
        $currentApprovals = [];
        $pendingApprovals = [];
        $referencedRunIds = [];
        foreach ($approvals as $approval) {
            $config = is_array($approval->approver_config) ? $approval->approver_config : [];
            $stepKey = trim((string)($config['award_approval_step_key'] ?? ''));
            if ($stepKey === '') {
                continue;
            }
            $ownedApprovals[] = $approval;
            $referencedRunId = (int)($config['award_approval_run_id'] ?? 0);
            if ($referencedRunId > 0) {
                $referencedRunIds[] = $referencedRunId;
            }
            if (
                in_array(
                    $approval->status,
                    [
                        WorkflowApproval::STATUS_PENDING,
                        WorkflowApproval::STATUS_APPROVED,
                        WorkflowApproval::STATUS_REJECTED,
                    ],
                    true,
                )
            ) {
                $currentApprovals[] = $approval;
            }
            if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                $pendingApprovals[] = $approval;
            }
        }

        if ($ownedApprovals === [] || $currentApprovals === []) {
            throw new RecommendationApprovalManualReviewException(
                RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                sprintf(
                    'Recommendation %d approval workflow ownership cannot be safely repaired: '
                    . 'no current configured award approval gate was found.',
                    $recommendationId,
                ),
            );
        }
        if (count($pendingApprovals) > 1) {
            throw new RecommendationApprovalManualReviewException(
                RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                sprintf(
                    'Recommendation %d approval workflow ownership cannot be safely repaired: '
                    . 'found %d pending award approval gates.',
                    $recommendationId,
                    count($pendingApprovals),
                ),
            );
        }

        $currentApproval = $pendingApprovals[0] ?? $currentApprovals[array_key_last($currentApprovals)];
        $currentConfig = is_array($currentApproval->approver_config) ? $currentApproval->approver_config : [];
        $currentStepKey = trim((string)($currentConfig['award_approval_step_key'] ?? ''));
        if ($currentStepKey === '') {
            throw new RecommendationApprovalManualReviewException(
                RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                sprintf(
                    'Recommendation %d approval workflow ownership cannot be safely repaired: '
                    . 'the current gate has no stable approval step key.',
                    $recommendationId,
                ),
            );
        }

        $referencedRunIds = array_values(array_unique($referencedRunIds));
        $referencedRuns = [];
        if ($referencedRunIds !== []) {
            $referencedRuns = $this->approvalRunsTable->find('withTrashed')
                ->where(['id IN' => $referencedRunIds])
                ->orderBy(['id' => 'ASC'])
                ->all()
                ->toList();
            foreach ($referencedRuns as $referencedRun) {
                if (
                    (int)$referencedRun->recommendation_id !== $recommendationId
                    || (int)$referencedRun->workflow_instance_id !== $instanceId
                ) {
                    throw new RecommendationApprovalManualReviewException(
                        RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                        sprintf(
                            'Recommendation %d approval workflow ownership cannot be safely repaired: '
                            . 'approval evidence references a run owned by another recommendation or workflow.',
                            $recommendationId,
                        ),
                    );
                }
            }
        }

        $process = $recommendation->award?->approval_process ?? null;
        if ($process === null || !$process->is_active) {
            throw new RecommendationApprovalManualReviewException(
                RecommendationApprovalManualReviewException::REASON_OWNERSHIP_AMBIGUOUS,
                sprintf(
                    'Recommendation %d approval workflow ownership cannot be safely repaired: '
                    . 'the award does not have an active approval process.',
                    $recommendationId,
                ),
            );
        }
        $currentStepLabel = $currentStepKey;
        foreach ($process->approval_process_steps ?? [] as $step) {
            if ((string)$step->step_key === $currentStepKey) {
                $currentStepLabel = (string)$step->label;
                break;
            }
        }

        $rehydratedFromRunId = null;
        foreach (array_reverse($referencedRuns) as $referencedRun) {
            if ($referencedRun->deleted === null) {
                $rehydratedFromRunId = (int)$referencedRun->id;
                break;
            }
        }
        $run = $this->approvalRunsTable->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => (int)$process->id,
            'workflow_instance_id' => $instanceId,
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => $currentStepKey,
            'current_step_label' => $currentStepLabel,
            'started' => $instance->started_at ?? $instance->created ?? DateTime::now(),
            'rehydrated_from_run_id' => $rehydratedFromRunId,
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]);
        $this->approvalRunsTable->saveOrFail($run);

        foreach ($ownedApprovals as $approval) {
            $config = is_array($approval->approver_config) ? $approval->approver_config : [];
            $config['award_approval_run_id'] = (int)$run->id;
            $approval->approver_config = $config;
            $this->workflowApprovalsTable->saveOrFail($approval);
        }

        $context = is_array($instance->context) ? $instance->context : [];
        if (is_array($context['awardApprovalCurrentStep']['approvalApproverConfig'] ?? null)) {
            $context['awardApprovalCurrentStep']['approvalApproverConfig']['award_approval_run_id'] = (int)$run->id;
        }
        if (is_array($context['nodes']['start-approval-process']['result'] ?? null)) {
            $context['nodes']['start-approval-process']['result']['runId'] = (int)$run->id;
        }
        $instance->context = $context;
        $this->workflowInstancesTable->saveOrFail($instance);

        return $run;
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
