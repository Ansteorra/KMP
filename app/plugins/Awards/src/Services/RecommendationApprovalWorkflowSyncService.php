<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Services\WorkflowEngine\WorkflowVersionManagerInterface;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;
use Throwable;

/**
 * Reconciles active award recommendation approvals with their current configuration.
 */
class RecommendationApprovalWorkflowSyncService
{
    use LocatorAwareTrait;

    private const SYNC_METADATA_KEY = 'award_workflow_sync';

    private const BACKFILL_FAILURE_REASON =
        'Recommendation ownership backfill failed. Review server logs for details.';

    private const ACTIVE_SYNC_FAILURE_REASON =
        'Active recommendation approval workflow synchronization failed. Review server logs for details.';

    private const APPROVAL_STATES = [
        'Submitted',
        'In Consideration',
        'Awaiting Feedback',
    ];

    private const OWNED_APPROVER_CONFIG_KEYS = [
        'service',
        'method',
        'award_approval_run_id',
        'award_approval_step_key',
        'award_approval_is_final_step',
        'award_approval_approver_type',
        'award_approval_approver_source_id',
        'award_approval_approver_source_key',
        'award_approval_branch_mode',
        'award_approval_branch_type',
        'award_approval_threshold_mode',
        'award_approval_required_count',
        'retain_read_visibility',
        'on_reject',
        'on_request_changes',
        'member_id',
        'current_approver_id',
        'eligible_member_ids',
        'blocked_no_approvers',
        'requires_bestowal_gathering',
    ];

    /**
     * @param \Awards\Services\RecommendationApprovalProcessService $approvalProcessService Process bridge.
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $workflowEngine Workflow runtime.
     * @param \App\Services\WorkflowEngine\WorkflowVersionManagerInterface $versionManager Version manager.
     * @param \Awards\Services\RecommendationMigrationService $migrationService Legacy ownership backfill.
     */
    public function __construct(
        private RecommendationApprovalProcessService $approvalProcessService,
        private WorkflowEngineInterface $workflowEngine,
        private WorkflowVersionManagerInterface $versionManager,
        private RecommendationMigrationService $migrationService,
    ) {
    }

    /**
     * Synchronize every active recommendation approval run.
     *
     * Each run resolves its target from the recommendation award, so awards may
     * use different processes. Failures are isolated to one run and reported.
     *
     * @param int $actorId Member performing the synchronization.
     * @return \App\Services\ServiceResult
     */
    public function syncOpenRecommendations(int $actorId): ServiceResult
    {
        try {
            $backfillResult = $this->migrationService->backfillOpenApprovalRecommendations($actorId);
            $backfill = is_array($backfillResult->getData()) ? $backfillResult->getData() : [];
        } catch (Throwable $exception) {
            $backfillResult = new ServiceResult(false, self::BACKFILL_FAILURE_REASON);
            $backfill = [];
            Log::error('Open award recommendation ownership backfill failed: ' . $exception->getMessage());
        }

        $backfillFailedCount = (int)($backfill['failedCount'] ?? 0);
        $backfillFailures = is_array($backfill['failures'] ?? null) ? $backfill['failures'] : [];
        if (!$backfillResult->isSuccess() && $backfillFailedCount === 0) {
            $backfillFailedCount = 1;
            $backfillFailures[] = [
                'recommendationId' => null,
                'reason' => $backfillResult->getError() ?? 'Recommendation ownership backfill failed.',
            ];
        }

        $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
        $runIds = $runsTable->find()
            ->select(['id'])
            ->where([
                'RecommendationApprovalRuns.status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->orderBy(['RecommendationApprovalRuns.id' => 'ASC'])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $summary = [
            'backfillCandidateCount' => (int)($backfill['candidateCount'] ?? 0),
            'backfilledCount' => (int)($backfill['startedCount'] ?? 0),
            'backfillUnchangedCount' => (int)($backfill['unchangedCount'] ?? 0),
            'backfillSkippedCount' => (int)($backfill['skippedCount'] ?? 0),
            'backfillFailedCount' => $backfillFailedCount,
            'backfillSkips' => is_array($backfill['skips'] ?? null) ? $backfill['skips'] : [],
            'processedCount' => 0,
            'synchronizedCount' => 0,
            'advancedCount' => 0,
            'versionMigratedCount' => 0,
            'unchangedCount' => 0,
            'activeRunSkippedCount' => 0,
            'activeRunFailedCount' => 0,
            'skippedCount' => (int)($backfill['skippedCount'] ?? 0),
            'failedCount' => $backfillFailedCount,
            'failures' => $backfillFailures,
        ];

        foreach ($runIds as $runId) {
            $summary['processedCount']++;
            try {
                $outcome = $this->syncRun($runId, $actorId);
                $status = (string)($outcome['status'] ?? 'synchronized');
                if ($status === 'advanced') {
                    $summary['advancedCount']++;
                    $summary['synchronizedCount']++;
                } elseif ($status === 'synchronized') {
                    $summary['synchronizedCount']++;
                } elseif ($status === 'unchanged') {
                    $summary['unchangedCount']++;
                } else {
                    $summary['activeRunSkippedCount']++;
                    $summary['skippedCount']++;
                }
                if (!empty($outcome['versionMigrated'])) {
                    $summary['versionMigratedCount']++;
                }
            } catch (Throwable $e) {
                $summary['activeRunFailedCount']++;
                $summary['failedCount']++;
                $summary['failures'][] = [
                    'runId' => $runId,
                    'reason' => self::ACTIVE_SYNC_FAILURE_REASON,
                ];
                Log::error(sprintf(
                    'Award recommendation approval sync failed for run %d: %s',
                    $runId,
                    $e->getMessage(),
                ));
            }
        }

        $success = $summary['failedCount'] === 0;

        return new ServiceResult(
            $success,
            $success ? null : 'One or more recommendation approval workflows could not be synchronized.',
            $summary,
        );
    }

    /**
     * Synchronize one active run atomically.
     *
     * @param int $runId Approval run ID.
     * @param int $actorId Synchronizing member ID.
     * @return array{status:string,versionMigrated:bool}
     */
    private function syncRun(int $runId, int $actorId): array
    {
        $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
        $connection = $runsTable->getConnection();
        // A resumed workflow can create a bestowal and synchronize its to-dos,
        // both of which open nested transactions on this same connection.
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        if (!$savePointsWereEnabled) {
            $connection->enableSavePoints();
        }

        $sync = function () use ($runsTable, $runId, $actorId): array {
            // Normal workflow resumption locks the instance before its domain run.
            // Resolve the immutable instance reference without a row lock, then
            // take synchronization locks in that same order to avoid deadlocks.
            $runReference = $runsTable->find()
                ->select(['id', 'workflow_instance_id'])
                ->where(['RecommendationApprovalRuns.id' => $runId])
                ->first();
            if (!$runReference instanceof RecommendationApprovalRun) {
                throw new RuntimeException("Recommendation approval run {$runId} was not found.");
            }
            $instanceId = (int)$runReference->workflow_instance_id;
            $instance = $this->lockWorkflowInstance($instanceId);

            $run = $runsTable->find()
                ->where(['RecommendationApprovalRuns.id' => $runId])
                ->epilog('FOR UPDATE')
                ->first();
            if (!$run instanceof RecommendationApprovalRun) {
                throw new RuntimeException("Recommendation approval run {$runId} was not found.");
            }
            if (
                !in_array(
                    $run->status,
                    [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                    true,
                )
            ) {
                return ['status' => 'skipped', 'versionMigrated' => false];
            }
            if ((int)$run->workflow_instance_id !== $instanceId) {
                throw new RuntimeException(sprintf(
                    'Recommendation approval run %d changed workflow instance during synchronization.',
                    $runId,
                ));
            }
            if (!in_array($instance->status, WorkflowInstance::ACTIVE_STATUSES, true)) {
                throw new RuntimeException("Workflow instance {$instanceId} is not active.");
            }
            $runsTable->loadInto($run, [
                'Recommendations.Awards' => [
                    'Branches',
                    'ApprovalProcesses.ApprovalProcessSteps',
                ],
            ]);

            $recommendation = $run->recommendation ?? null;
            if (!$recommendation instanceof Recommendation) {
                throw new RuntimeException("Recommendation approval run {$runId} has no recommendation.");
            }
            if (!$this->isRecommendationEligibleForSync($recommendation)) {
                return ['status' => 'skipped', 'versionMigrated' => false];
            }
            $approval = $this->lockCurrentApproval($run);
            $versionMigrated = $this->migrateWorkflowVersion($instance, $actorId);
            if ($versionMigrated) {
                $instance = $this->fetchTable('WorkflowInstances')->get((int)$instance->id);
            }
            if ($approval->status === WorkflowApproval::STATUS_REJECTED) {
                $this->resumeWorkflowFromGate($approval, $instance, $actorId);

                return ['status' => 'advanced', 'versionMigrated' => $versionMigrated];
            }

            $process = $recommendation->award?->approval_process ?? null;
            if ($process === null || !$process->is_active) {
                throw new RuntimeException('The recommendation award does not have an active approval process.');
            }

            $steps = $this->validateAndOrderSteps($process->approval_process_steps ?? []);
            $completedStepKeys = $this->approvalProcessService->completedStepKeys($run);
            $currentStepKey = (string)$run->current_step_key;
            $targetStep = $this->findStep($steps, $currentStepKey);
            if ($targetStep === null) {
                $targetStep = $this->firstIncompleteStep($steps, $completedStepKeys);
            }

            $allCurrentStepsComplete = $targetStep === null;
            $targetStep ??= $steps[array_key_last($steps)];
            $oldProcessId = (int)$run->approval_process_id;
            $oldStepKey = $currentStepKey;
            $targetStepKey = (string)$targetStep->step_key;
            $sameStep = $targetStepKey === $oldStepKey;

            $runChanged = $oldProcessId !== (int)$process->id
                || $oldStepKey !== $targetStepKey
                || (string)$run->current_step_label !== (string)$targetStep->label;
            if ($runChanged) {
                $run->approval_process_id = (int)$process->id;
                $run->current_step_key = $targetStepKey;
                $run->current_step_label = (string)$targetStep->label;
                $run->modified_by = $actorId;
                $runsTable->saveOrFail($run);
            }

            $stepOutput = $this->approvalProcessService->buildStepOutput(
                $run,
                $recommendation,
                $targetStep,
                (int)$approval->id,
                $completedStepKeys,
            );
            $desiredConfig = $this->desiredApproverConfig($approval, $stepOutput);
            $requiredCount = max(1, (int)($stepOutput['requiredCount'] ?? 1));
            $desiredCurrentApproverId = $this->desiredCurrentApproverId($stepOutput);
            if (!$sameStep || $allCurrentStepsComplete) {
                if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                    $this->cancelPendingApproval(
                        $approval,
                        $actorId,
                        $oldProcessId,
                        (int)$process->id,
                        $oldStepKey,
                        $targetStepKey,
                    );
                }
                $this->syncInstanceContext($instance, $stepOutput, $desiredConfig, $requiredCount);

                if ($allCurrentStepsComplete) {
                    $this->resumeWorkflowFromGate(
                        $approval,
                        $instance,
                        $actorId,
                        WorkflowApproval::STATUS_APPROVED,
                    );

                    return ['status' => 'advanced', 'versionMigrated' => $versionMigrated];
                }

                $this->createReplacementApproval(
                    $approval,
                    $desiredConfig,
                    $requiredCount,
                    $desiredCurrentApproverId,
                    $actorId,
                    $oldProcessId,
                    (int)$process->id,
                );

                return ['status' => 'synchronized', 'versionMigrated' => $versionMigrated];
            }

            $thresholdReached = !empty($stepOutput['approverIds'])
                && (int)$approval->approved_count >= $requiredCount;
            $configChanged = $desiredConfig != ($approval->approver_config ?? []);
            $approvalChanged = $configChanged
                || (int)$approval->required_count !== $requiredCount
                || $approval->current_approver_id !== $desiredCurrentApproverId
                || (
                    $approval->status === WorkflowApproval::STATUS_APPROVED
                    && !$thresholdReached
                );
            $contextChanged = $this->instanceContextNeedsSync(
                $instance,
                $stepOutput,
                $desiredConfig,
                $requiredCount,
            );
            $changed = $runChanged
                || $approvalChanged
                || $contextChanged
                || $versionMigrated
                || $thresholdReached;
            if (!$changed) {
                return ['status' => 'unchanged', 'versionMigrated' => false];
            }

            $desiredConfig[self::SYNC_METADATA_KEY] = $this->syncMetadata(
                $actorId,
                $oldProcessId,
                (int)$process->id,
                $oldStepKey,
                $targetStepKey,
            );
            $approval->approver_config = $desiredConfig;
            $approval->required_count = $requiredCount;
            $approval->current_approver_id = $desiredCurrentApproverId;
            $approval->version = (int)($approval->version ?? 1) + 1;
            if ($thresholdReached) {
                $approval->status = WorkflowApproval::STATUS_APPROVED;
            } elseif ($approval->status === WorkflowApproval::STATUS_APPROVED) {
                $approval->status = WorkflowApproval::STATUS_PENDING;
            }
            $this->fetchTable('WorkflowApprovals')->saveOrFail($approval);
            $this->syncInstanceContext($instance, $stepOutput, $desiredConfig, $requiredCount);

            if ($thresholdReached) {
                $this->resumeWorkflowFromGate($approval, $instance, $actorId);

                return ['status' => 'advanced', 'versionMigrated' => $versionMigrated];
            }

            return ['status' => 'synchronized', 'versionMigrated' => $versionMigrated];
        };

        try {
            return $connection->transactional($sync);
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /**
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Process steps.
     * @return array<int, \Awards\Model\Entity\ApprovalProcessStep>
     */
    private function validateAndOrderSteps(array $steps): array
    {
        usort($steps, static fn($left, $right): int => ((int)$left->sequence) <=> ((int)$right->sequence));
        $keys = [];
        foreach ($steps as $step) {
            $key = trim((string)$step->step_key);
            if ($key === '') {
                throw new RuntimeException('The current approval process contains a blank step key.');
            }
            if (isset($keys[$key])) {
                throw new RuntimeException("The current approval process contains duplicate step key '{$key}'.");
            }
            $keys[$key] = true;
        }
        if ($steps === []) {
            throw new RuntimeException('The current approval process does not have any approval steps.');
        }

        return array_values($steps);
    }

    /**
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Process steps.
     * @param string $stepKey Stable step key.
     */
    private function findStep(array $steps, string $stepKey): ?ApprovalProcessStep
    {
        foreach ($steps as $step) {
            if ((string)$step->step_key === $stepKey) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Process steps.
     * @param array<int, string> $completedStepKeys Completed stable keys.
     */
    private function firstIncompleteStep(array $steps, array $completedStepKeys): ?ApprovalProcessStep
    {
        foreach ($steps as $step) {
            if (!in_array((string)$step->step_key, $completedStepKeys, true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Only approval-owned, ungrouped recommendations without a bestowal may advance.
     */
    private function isRecommendationEligibleForSync(Recommendation $recommendation): bool
    {
        return (string)$recommendation->status !== 'Closed'
            && in_array((string)$recommendation->state, self::APPROVAL_STATES, true)
            && $recommendation->bestowal_id === null
            && $recommendation->recommendation_group_id === null;
    }

    /**
     * Lock the workflow instance owned by a run.
     *
     * The instance row is locked before its owning run. The caller validates
     * active state after both locks are held so their snapshot is evaluated together.
     */
    private function lockWorkflowInstance(int $instanceId): WorkflowInstance
    {
        $instance = $this->fetchTable('WorkflowInstances')->find()
            ->where(['WorkflowInstances.id' => $instanceId])
            ->epilog('FOR UPDATE')
            ->first();
        if (!$instance instanceof WorkflowInstance) {
            throw new RuntimeException("Workflow instance {$instanceId} was not found.");
        }

        return $instance;
    }

    /**
     * Resolve the current Awards gate for an active run.
     *
     * A response is committed before the workflow engine resumes. If execution
     * stopped in that narrow window, the current gate is already resolved and
     * must be returned so synchronization can safely resume it.
     */
    private function lockCurrentApproval(RecommendationApprovalRun $run): WorkflowApproval
    {
        $currentStepKey = trim((string)$run->current_step_key);
        if ($currentStepKey === '') {
            throw new RuntimeException(sprintf(
                'Active recommendation approval run %d does not have a current step key.',
                (int)$run->id,
            ));
        }
        $approvals = $this->fetchTable('WorkflowApprovals')->find()
            ->where([
                'WorkflowApprovals.workflow_instance_id' => (int)$run->workflow_instance_id,
                'WorkflowApprovals.status IN' => [
                    WorkflowApproval::STATUS_PENDING,
                    WorkflowApproval::STATUS_APPROVED,
                    WorkflowApproval::STATUS_REJECTED,
                ],
            ])
            ->orderBy(['WorkflowApprovals.id' => 'DESC'])
            ->epilog('FOR UPDATE')
            ->all();
        $pendingMatches = [];
        $resolvedCurrentMatches = [];
        foreach ($approvals as $approval) {
            $config = is_array($approval->approver_config) ? $approval->approver_config : [];
            if ((int)($config['award_approval_run_id'] ?? 0) !== (int)$run->id) {
                continue;
            }
            $approvalStepKey = trim((string)($config['award_approval_step_key'] ?? ''));
            if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                if ($approvalStepKey !== $currentStepKey) {
                    throw new RuntimeException(sprintf(
                        'Pending award approval gate %d has step key "%s"; active run %d expects "%s".',
                        (int)$approval->id,
                        $approvalStepKey,
                        (int)$run->id,
                        $currentStepKey,
                    ));
                }
                $pendingMatches[] = $approval;

                continue;
            }
            if (
                in_array($approval->status, [
                    WorkflowApproval::STATUS_APPROVED,
                    WorkflowApproval::STATUS_REJECTED,
                ], true)
                && $approvalStepKey === $currentStepKey
            ) {
                $resolvedCurrentMatches[] = $approval;
            }
        }
        if (count($pendingMatches) === 1 && $pendingMatches[0] instanceof WorkflowApproval) {
            return $pendingMatches[0];
        }
        if (count($pendingMatches) > 1) {
            throw new RuntimeException(sprintf(
                'Expected one pending award approval gate for run %d; found %d.',
                (int)$run->id,
                count($pendingMatches),
            ));
        }
        if ($resolvedCurrentMatches !== [] && $resolvedCurrentMatches[0] instanceof WorkflowApproval) {
            return $resolvedCurrentMatches[0];
        }

        throw new RuntimeException(sprintf(
            'Expected a current pending or resolved award approval gate for run %d; found none.',
            (int)$run->id,
        ));
    }

    /**
     * @param array<string, mixed> $stepOutput Current step projection.
     * @return array<string, mixed>
     */
    private function desiredApproverConfig(WorkflowApproval $approval, array $stepOutput): array
    {
        $config = is_array($approval->approver_config) ? $approval->approver_config : [];
        foreach (self::OWNED_APPROVER_CONFIG_KEYS as $key) {
            unset($config[$key]);
        }
        $currentConfig = $stepOutput['approvalApproverConfig'] ?? [];
        if (!is_array($currentConfig)) {
            throw new RuntimeException('The current approval step produced an invalid approver configuration.');
        }
        $approverIds = array_values(array_unique(array_map('intval', $stepOutput['approverIds'] ?? [])));

        return $config + $currentConfig + [
            'eligible_member_ids' => $approverIds,
            'blocked_no_approvers' => $approverIds === [],
            'requires_bestowal_gathering' => !empty($currentConfig['award_approval_is_final_step']),
        ];
    }

    /**
     * @param array<string, mixed> $stepOutput Current step projection.
     */
    private function desiredCurrentApproverId(array $stepOutput): ?int
    {
        $approverIds = array_values(array_unique(array_map('intval', $stepOutput['approverIds'] ?? [])));

        return count($approverIds) === 1 ? $approverIds[0] : null;
    }

    /**
     * Migrate an instance to the published version of its existing definition.
     */
    private function migrateWorkflowVersion(WorkflowInstance $instance, int $actorId): bool
    {
        $target = $this->versionManager->getCurrentVersion((int)$instance->workflow_definition_id);
        if ($target === null) {
            throw new RuntimeException('The recommendation workflow does not have a published current version.');
        }
        if ((int)$target->workflow_definition_id !== (int)$instance->workflow_definition_id) {
            throw new RuntimeException('The current workflow version belongs to a different definition.');
        }
        if ((int)$target->id === (int)$instance->workflow_version_id) {
            return false;
        }

        $result = $this->versionManager->migrateInstance((int)$instance->id, (int)$target->id, $actorId);
        if (!$result->isSuccess()) {
            throw new RuntimeException(
                $result->getError() ?? 'The recommendation workflow version could not be upgraded.',
            );
        }

        return true;
    }

    /**
     * @param array<string, mixed> $stepOutput Current step projection.
     * @param array<string, mixed> $desiredConfig Current gate config.
     */
    private function instanceContextNeedsSync(
        WorkflowInstance $instance,
        array $stepOutput,
        array $desiredConfig,
        int $requiredCount,
    ): bool {
        $context = is_array($instance->context) ? $instance->context : [];

        return ($context['awardApprovalCurrentStep'] ?? null)
            != $this->currentStepContext($stepOutput, $desiredConfig, $requiredCount);
    }

    /**
     * @param array<string, mixed> $stepOutput Current step projection.
     * @param array<string, mixed> $desiredConfig Current gate config.
     */
    private function syncInstanceContext(
        WorkflowInstance $instance,
        array $stepOutput,
        array $desiredConfig,
        int $requiredCount,
    ): void {
        $context = is_array($instance->context) ? $instance->context : [];
        $currentStepContext = $this->currentStepContext($stepOutput, $desiredConfig, $requiredCount);
        if (($context['awardApprovalCurrentStep'] ?? null) == $currentStepContext) {
            return;
        }
        $context['awardApprovalCurrentStep'] = $currentStepContext;
        $instance->context = $context;
        $this->fetchTable('WorkflowInstances')->saveOrFail($instance);
    }

    /**
     * @param array<string, mixed> $stepOutput Current step projection.
     * @param array<string, mixed> $desiredConfig Current gate config.
     * @return array<string, mixed>
     */
    private function currentStepContext(array $stepOutput, array $desiredConfig, int $requiredCount): array
    {
        $contextConfig = $desiredConfig;
        unset(
            $contextConfig['eligible_member_ids'],
            $contextConfig['blocked_no_approvers'],
            $contextConfig[self::SYNC_METADATA_KEY],
        );

        return [
            'approvalApproverConfig' => $contextConfig,
            'requiredCount' => $requiredCount,
            'currentStepKey' => $stepOutput['currentStepKey'] ?? null,
            'currentStepLabel' => $stepOutput['currentStepLabel'] ?? null,
        ];
    }

    /**
     * Cancel an obsolete pending gate without deleting its responses.
     */
    private function cancelPendingApproval(
        WorkflowApproval $approval,
        int $actorId,
        int $oldProcessId,
        int $newProcessId,
        string $oldStepKey,
        string $newStepKey,
    ): void {
        $config = is_array($approval->approver_config) ? $approval->approver_config : [];
        $config[self::SYNC_METADATA_KEY] = $this->syncMetadata(
            $actorId,
            $oldProcessId,
            $newProcessId,
            $oldStepKey,
            $newStepKey,
        ) + ['cancelled_for_retarget' => true];
        $approval->approver_config = $config;
        $approval->status = WorkflowApproval::STATUS_CANCELLED;
        $approval->version = (int)($approval->version ?? 1) + 1;
        $this->fetchTable('WorkflowApprovals')->saveOrFail($approval);
    }

    /**
     * Create a new gate on the existing waiting node when the current step was removed.
     *
     * @param array<string, mixed> $desiredConfig Current gate config.
     */
    private function createReplacementApproval(
        WorkflowApproval $oldApproval,
        array $desiredConfig,
        int $requiredCount,
        ?int $currentApproverId,
        int $actorId,
        int $oldProcessId,
        int $newProcessId,
    ): void {
        $desiredConfig[self::SYNC_METADATA_KEY] = $this->syncMetadata(
            $actorId,
            $oldProcessId,
            $newProcessId,
            (string)($oldApproval->approver_config['award_approval_step_key'] ?? ''),
            (string)($desiredConfig['award_approval_step_key'] ?? ''),
        ) + ['replacement_for_approval_id' => (int)$oldApproval->id];
        $approvalsTable = $this->fetchTable('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => (int)$oldApproval->workflow_instance_id,
            'node_id' => (string)$oldApproval->node_id,
            'execution_log_id' => (int)$oldApproval->execution_log_id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => $desiredConfig,
            'current_approver_id' => $currentApproverId,
            'request_title' => $oldApproval->request_title,
            'required_count' => $requiredCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => (bool)$oldApproval->allow_parallel,
            'deadline' => $oldApproval->deadline,
            'escalation_config' => $oldApproval->escalation_config,
            'version' => 1,
            'approval_token' => StaticHelpers::generateToken(32),
        ]);
        $approvalsTable->saveOrFail($approval);
    }

    /**
     * Resume a resolved gate or a gate made obsolete after all current work completed.
     */
    private function resumeWorkflowFromGate(
        WorkflowApproval $approval,
        WorkflowInstance $instance,
        int $actorId,
        ?string $resolutionStatus = null,
    ): void {
        $resolutionStatus ??= (string)$approval->status;
        if (
            !in_array(
                $resolutionStatus,
                [
                    WorkflowApproval::STATUS_APPROVED,
                    WorkflowApproval::STATUS_REJECTED,
                ],
                true,
            )
        ) {
            throw new RuntimeException('Only a resolved approval gate can resume its workflow.');
        }
        $outputPort = $resolutionStatus === WorkflowApproval::STATUS_APPROVED ? 'approved' : 'rejected';
        $decision = $resolutionStatus === WorkflowApproval::STATUS_APPROVED ? 'approve' : 'reject';
        $response = $this->fetchTable('WorkflowApprovalResponses')->find()
            ->where([
                'workflow_approval_id' => (int)$approval->id,
                'decision' => $decision,
            ])
            ->orderByDesc('id')
            ->first();
        $responderId = (int)($response?->member_id ?? $actorId);
        $approvalData = [
            'approvalId' => (int)$approval->id,
            'instanceId' => (int)$approval->workflow_instance_id,
            'nodeId' => (string)$approval->node_id,
            'approvalStatus' => $resolutionStatus,
            'synchronized' => true,
        ];
        $resumeData = [
            'approval' => $approvalData,
            'approvalStatus' => $resolutionStatus,
            'approverId' => $responderId,
            'decision' => $decision,
            'synchronized' => true,
        ];
        $comment = trim((string)($response?->comment ?? ''));
        if ($comment !== '') {
            $resumeData['comment'] = $comment;
        }
        $approvalConfig = is_array($approval->approver_config) ? $approval->approver_config : [];
        $context = is_array($instance->context) ? $instance->context : [];
        $nodeContext = $context['nodes'][(string)$approval->node_id] ?? [];
        $bestowalGatheringId = (int)($approvalConfig['bestowal_gathering_id'] ?? 0);
        if ($bestowalGatheringId <= 0 && is_array($nodeContext)) {
            $bestowalGatheringId = (int)($nodeContext['bestowalGatheringId'] ?? 0);
        }
        if ($bestowalGatheringId > 0) {
            $resumeData['bestowalGatheringId'] = $bestowalGatheringId;
        }

        $result = $this->workflowEngine->resumeWorkflow(
            (int)$approval->workflow_instance_id,
            (string)$approval->node_id,
            $outputPort,
            $resumeData,
        );
        if (!$result->isSuccess()) {
            throw new RuntimeException($result->getError() ?? 'The synchronized workflow could not be advanced.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function syncMetadata(
        int $actorId,
        int $oldProcessId,
        int $newProcessId,
        string $oldStepKey,
        string $newStepKey,
    ): array {
        return [
            'synced_at' => DateTime::now()->toAtomString(),
            'synced_by' => $actorId,
            'from_approval_process_id' => $oldProcessId,
            'to_approval_process_id' => $newProcessId,
            'from_step_key' => $oldStepKey,
            'to_step_key' => $newStepKey,
        ];
    }
}
