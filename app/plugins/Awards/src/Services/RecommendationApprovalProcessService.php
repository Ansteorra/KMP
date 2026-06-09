<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowApproval;
use App\Services\ServiceResult;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;
use Throwable;

/**
 * Bridges award recommendation approval process config to workflow runtime state.
 */
class RecommendationApprovalProcessService
{
    use LocatorAwareTrait;

    private AwardApprovalResolverService $resolver;
    private RecommendationTransitionService $transitionService;

    /**
     * @param \Awards\Services\AwardApprovalResolverService|null $resolver Branch-aware approver resolver.
     * @param \Awards\Services\RecommendationTransitionService|null $transitionService State transition service.
     */
    public function __construct(
        ?AwardApprovalResolverService $resolver = null,
        ?RecommendationTransitionService $transitionService = null,
    ) {
        $this->resolver = $resolver ?? new AwardApprovalResolverService();
        $this->transitionService = $transitionService ?? new RecommendationTransitionService();
    }

    /**
     * Start or reuse an approval run for the current workflow instance.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return \App\Services\ServiceResult
     */
    public function startProcess(array $context, array $config): ServiceResult
    {
        try {
            $instanceId = $this->resolveInstanceId($context);
            $actorId = $this->resolveActorId($context, $config);
            $recommendation = $this->loadGroupHeadRecommendation($this->resolveRecommendationId($context, $config));
            $process = $recommendation->award->approval_process ?? null;
            if (!$process || !$process->is_active) {
                return new ServiceResult(false, 'The recommendation award does not have an active approval process.');
            }

            $steps = $this->orderedSteps($process->approval_process_steps ?? []);
            if ($steps === []) {
                return new ServiceResult(false, 'The approval process does not have any approval steps.');
            }

            $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
            $run = $runsTable->find()
                ->where([
                    'RecommendationApprovalRuns.workflow_instance_id' => $instanceId,
                    'RecommendationApprovalRuns.recommendation_id' => $recommendation->id,
                    'RecommendationApprovalRuns.status IN' => [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                ])
                ->first();

            $firstStep = $steps[0];
            if (!$run) {
                $trigger = $context['trigger'] ?? [];
                $run = $runsTable->newEntity([
                    'recommendation_id' => $recommendation->id,
                    'approval_process_id' => $process->id,
                    'workflow_instance_id' => $instanceId,
                    'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    'current_step_key' => $firstStep->step_key,
                    'current_step_label' => $firstStep->label,
                    'started' => DateTime::now(),
                    'rehydrated_from_run_id' => !empty($trigger['rehydratedFromRunId'])
                        ? (int)$trigger['rehydratedFromRunId']
                        : null,
                    'created_by' => $actorId,
                    'modified_by' => $actorId,
                ]);
                if (!$runsTable->save($run)) {
                    return new ServiceResult(false, 'The recommendation approval run could not be saved.');
                }
            }

            return new ServiceResult(true, null, $this->stepOutput($run, $recommendation, $firstStep));
        } catch (Throwable $e) {
            Log::error('Award approval process start failed: ' . $e->getMessage());

            return new ServiceResult(false, $e->getMessage());
        }
    }

    /**
     * Advance the run projection after a workflow approval node resolves.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return \App\Services\ServiceResult
     */
    public function advanceProcess(array $context, array $config): ServiceResult
    {
        try {
            $instanceId = $this->resolveInstanceId($context);
            $actorId = $this->resolveActorId($context, $config);
            $approvalStatus = $this->resolveApprovalStatus($context, $config);
            if ($approvalStatus === '') {
                return new ServiceResult(false, 'Approval status is required to advance an approval process.');
            }

            $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
            $run = $runsTable->find()
                ->contain(['ApprovalProcesses.ApprovalProcessSteps', 'Recommendations.Awards.ApprovalProcesses'])
                ->where([
                    'RecommendationApprovalRuns.workflow_instance_id' => $instanceId,
                    'RecommendationApprovalRuns.status IN' => [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                ])
                ->orderBy(['RecommendationApprovalRuns.id' => 'DESC'])
                ->first();
            if (!$run) {
                return new ServiceResult(false, 'No active recommendation approval run was found.');
            }

            $recommendation = $this->loadGroupHeadRecommendation((int)$run->recommendation_id);
            $steps = $this->orderedSteps($run->approval_process->approval_process_steps ?? []);
            $currentIndex = $this->findStepIndex($steps, (string)$run->current_step_key);
            if ($currentIndex === null) {
                return new ServiceResult(false, 'The current approval process step could not be found.');
            }

            if ($approvalStatus === 'rejected') {
                return $this->handleRejectedStep(
                    $run,
                    $recommendation,
                    $actorId,
                    $this->resolveRejectionComment($context, $config),
                );
            }

            if ($approvalStatus !== 'approved') {
                return new ServiceResult(false, 'Only approved or rejected approval statuses can advance a process.');
            }

            $nextStep = $steps[$currentIndex + 1] ?? null;
            if ($nextStep) {
                $this->updateRunStep($run, $nextStep, RecommendationApprovalRun::STATUS_IN_PROGRESS, $actorId);

                return new ServiceResult(true, null, $this->stepOutput($run, $recommendation, $nextStep));
            }

            $run->status = RecommendationApprovalRun::STATUS_APPROVED;
            $run->current_step_key = null;
            $run->current_step_label = null;
            $run->completed = DateTime::now();
            $run->modified_by = $actorId;
            $runsTable->saveOrFail($run);

            return new ServiceResult(true, null, [
                'runId' => (int)$run->id,
                'status' => $run->status,
                'completed' => true,
                'recommendationId' => (int)$recommendation->id,
            ]);
        } catch (Throwable $e) {
            Log::error('Award approval process advance failed: ' . $e->getMessage());

            return new ServiceResult(false, $e->getMessage());
        }
    }

    /**
     * Dynamic workflow approval callback for configured award approval steps.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Workflow approval.
     * @return array<int>
     */
    public function resolveConfiguredApproverIds(WorkflowApproval $approval): array
    {
        $config = $approval->approver_config ?? [];

        try {
            $runsTable = $this->fetchTable('Awards.RecommendationApprovalRuns');
            $run = $runsTable->find()
                ->contain(['Recommendations.Awards.ApprovalProcesses.ApprovalProcessSteps'])
                ->where([
                    'RecommendationApprovalRuns.workflow_instance_id' => $approval->workflow_instance_id,
                    'RecommendationApprovalRuns.status IN' => [
                        RecommendationApprovalRun::STATUS_IN_PROGRESS,
                        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                    ],
                ])
                ->orderBy(['RecommendationApprovalRuns.id' => 'DESC'])
                ->first();
            if (!$run) {
                return [];
            }

            $recommendation = $this->loadGroupHeadRecommendation((int)$run->recommendation_id);
            $step = $this->configuredStep($config);
            if ($step === null) {
                $steps = $this->orderedSteps($recommendation->award->approval_process->approval_process_steps ?? []);
                $stepKey = (string)($config['award_approval_step_key'] ?? $run->current_step_key);
                $stepIndex = $this->findStepIndex($steps, $stepKey);
                if ($stepIndex === null) {
                    return [];
                }
                $step = $steps[$stepIndex];
            }

            if (
                $step->approver_type !== ApprovalProcessStep::APPROVER_TYPE_MEMBER
                && empty($step->approver_source_id)
            ) {
                return [];
            }

            $approverIds = $this->excludePriorApprovalResponders(
                (int)$approval->workflow_instance_id,
                $this->approverIds($step, $recommendation),
                (int)$approval->id,
            );
            $this->syncRequiredCount($approval, $config, $approverIds);

            return $approverIds;
        } catch (Throwable $e) {
            Log::error('Award approval dynamic approver resolution failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Load a recommendation group head with approval process context.
     *
     * @param int $recommendationId Recommendation or child recommendation ID.
     * @return \Awards\Model\Entity\Recommendation
     */
    private function loadGroupHeadRecommendation(int $recommendationId): Recommendation
    {
        $recommendations = $this->fetchTable('Awards.Recommendations');
        $recommendation = $recommendations->get($recommendationId, contain: [
            'Awards' => ['Branches', 'ApprovalProcesses' => ['ApprovalProcessSteps']],
        ]);

        if ($recommendation->recommendation_group_id !== null) {
            $recommendation = $recommendations->get((int)$recommendation->recommendation_group_id, contain: [
                'Awards' => ['Branches', 'ApprovalProcesses' => ['ApprovalProcessSteps']],
            ]);
        }

        return $recommendation;
    }

    /**
     * Build workflow action output for one step.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step.
     * @return array<string, mixed>
     */
    private function stepOutput(
        RecommendationApprovalRun $run,
        Recommendation $recommendation,
        ApprovalProcessStep $step,
    ): array {
        $approverIds = $this->excludePriorApprovalResponders(
            (int)$run->workflow_instance_id,
            $this->approverIds($step, $recommendation),
        );
        $requiredCount = $this->requiredCount($step, $approverIds);

        return [
            'runId' => (int)$run->id,
            'status' => $run->status,
            'recommendationId' => (int)$run->recommendation_id,
            'approvalProcessId' => (int)$run->approval_process_id,
            'currentStepKey' => (string)$step->step_key,
            'currentStepLabel' => (string)$step->label,
            'approverIds' => $approverIds,
            'requiredCount' => $requiredCount,
            'approvalApproverConfig' => $this->approvalApproverConfig($run, $step),
        ];
    }

    /**
     * Build the dynamic approver resolver config.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step.
     * @return array<string, mixed>
     */
    private function approvalApproverConfig(RecommendationApprovalRun $run, ApprovalProcessStep $step): array
    {
        $config = [
            'service' => 'Awards.ResolveApprovalStepApprovers',
            'method' => 'resolveConfiguredApproverIds',
            'award_approval_run_id' => (int)$run->id,
            'award_approval_step_key' => (string)$step->step_key,
            'award_approval_approver_type' => (string)$step->approver_type,
            'award_approval_approver_source_id' => $step->approver_source_id !== null
                ? (int)$step->approver_source_id
                : null,
            'award_approval_approver_source_key' => $step->approver_source_key,
            'award_approval_branch_mode' => (string)$step->branch_mode,
            'award_approval_branch_type' => $step->branch_type,
            'award_approval_threshold_mode' => (string)$step->threshold_mode,
            'award_approval_required_count' => $step->required_count !== null ? (int)$step->required_count : null,
            'retain_read_visibility' => (bool)$step->retain_read_visibility,
            'on_reject' => (string)$step->on_reject,
            'on_request_changes' => (string)$step->on_request_changes,
        ];

        if ($step->approver_type === ApprovalProcessStep::APPROVER_TYPE_MEMBER) {
            $config['member_id'] = (int)$step->approver_source_id;
        }

        return $config;
    }

    /**
     * Rehydrate a configured approval target from workflow approval config.
     *
     * @param array<string, mixed> $config Workflow approval approver config.
     * @return \Awards\Model\Entity\ApprovalProcessStep|null
     */
    private function configuredStep(array $config): ?ApprovalProcessStep
    {
        if (empty($config['award_approval_approver_type'])) {
            return null;
        }

        return new ApprovalProcessStep([
            'step_key' => $config['award_approval_step_key'] ?? null,
            'approver_type' => $config['award_approval_approver_type'],
            'approver_source_id' => $config['award_approval_approver_source_id'] ?? ($config['member_id'] ?? null),
            'approver_source_key' => $config['award_approval_approver_source_key'] ?? null,
            'branch_mode' => $config['award_approval_branch_mode'] ?? ApprovalProcessStep::BRANCH_MODE_AWARD,
            'branch_type' => $config['award_approval_branch_type'] ?? null,
        ]);
    }

    /**
     * Keep dynamic workflow approval counts aligned with the current target pool.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Workflow approval.
     * @param array<string, mixed> $config Workflow approval approver config.
     * @param array<int> $approverIds Current approver IDs.
     * @return void
     */
    private function syncRequiredCount(WorkflowApproval $approval, array $config, array $approverIds): void
    {
        $thresholdMode = $config['award_approval_threshold_mode'] ?? null;
        $requiredCount = match ($thresholdMode) {
            ApprovalProcessStep::THRESHOLD_ALL => count($approverIds),
            ApprovalProcessStep::THRESHOLD_COUNT => min(
                (int)($config['award_approval_required_count'] ?? $approval->required_count),
                count($approverIds),
            ),
            default => (int)$approval->required_count,
        };
        $requiredCount = max(1, $requiredCount);

        if (
            (int)$approval->required_count !== $requiredCount
            && !empty($approval->id)
            && $approval->status === WorkflowApproval::STATUS_PENDING
        ) {
            $this->fetchTable('WorkflowApprovals')->updateAll(
                ['required_count' => $requiredCount],
                ['id' => (int)$approval->id, 'status' => WorkflowApproval::STATUS_PENDING],
            );
            $approval->required_count = $requiredCount;
        }
    }

    /**
     * Resolve approver IDs for a process step.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @return array<int>
     */
    private function approverIds(ApprovalProcessStep $step, Recommendation $recommendation): array
    {
        $members = $this->resolver->resolveApprovers($step, $recommendation->award);
        $ids = array_values(array_unique(array_map(static fn($member): int => (int)$member->id, $members)));
        if ($ids === []) {
            throw new RuntimeException('The approval step resolved zero eligible approvers.');
        }

        return $ids;
    }

    /**
     * Remove members who have already responded to a prior approval in this workflow instance.
     *
     * @param int $workflowInstanceId Workflow instance ID.
     * @param array<int> $approverIds Candidate approver IDs.
     * @return array<int>
     */
    private function excludePriorApprovalResponders(
        int $workflowInstanceId,
        array $approverIds,
        ?int $currentApprovalId = null,
    ): array {
        if ($workflowInstanceId <= 0 || $approverIds === []) {
            return $approverIds;
        }

        $responses = $this->fetchTable('WorkflowApprovalResponses');
        $query = $responses->find()
            ->select(['member_id'])
            ->innerJoinWith('WorkflowApprovals', function ($q) use ($workflowInstanceId) {
                return $q->where(['WorkflowApprovals.workflow_instance_id' => $workflowInstanceId]);
            })
            ->where(['WorkflowApprovalResponses.member_id IN' => $approverIds]);
        if ($currentApprovalId !== null) {
            $query->where(['WorkflowApprovalResponses.workflow_approval_id !=' => $currentApprovalId]);
        }

        $priorResponderIds = $query
            ->all()
            ->extract('member_id')
            ->map(static fn($memberId): int => (int)$memberId)
            ->toList();

        if ($priorResponderIds === []) {
            return $approverIds;
        }

        $eligibleIds = array_values(array_diff($approverIds, array_unique($priorResponderIds)));
        if ($eligibleIds === []) {
            throw new RuntimeException('The approval step resolved zero eligible approvers after excluding prior responders.');
        }

        return $eligibleIds;
    }

    /**
     * Compute required count from the configured threshold.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step.
     * @param array<int> $approverIds Eligible member IDs.
     * @return int
     */
    private function requiredCount(ApprovalProcessStep $step, array $approverIds): int
    {
        return match ($step->threshold_mode) {
            ApprovalProcessStep::THRESHOLD_ALL => count($approverIds),
            ApprovalProcessStep::THRESHOLD_COUNT => min((int)$step->required_count, count($approverIds)),
            default => 1,
        };
    }

    /**
     * Close a rejected approval run.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param int|null $actorId Actor ID.
     * @param string|null $closeReason Rejection comment to store as the close reason.
     * @return \App\Services\ServiceResult
     */
    private function handleRejectedStep(
        RecommendationApprovalRun $run,
        Recommendation $recommendation,
        ?int $actorId,
        ?string $closeReason,
    ): ServiceResult {
        $run->status = RecommendationApprovalRun::STATUS_CLOSED;
        $run->completed = DateTime::now();
        $run->terminal_reason = RecommendationApprovalRun::TERMINAL_REASON_REJECTED;
        $run->modified_by = $actorId;
        $this->fetchTable('Awards.RecommendationApprovalRuns')->saveOrFail($run);
        $this->transitionRecommendation(
            $recommendation,
            RecommendationBestowalStatePolicyService::NO_ACTION_STATE,
            $actorId,
            $closeReason,
        );

        return new ServiceResult(true, null, [
            'runId' => (int)$run->id,
            'status' => $run->status,
            'closed' => true,
        ]);
    }

    /**
     * Resolve a kickback action to a target step.
     *
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Ordered steps.
     * @param int $currentIndex Current step index.
     * @param string $action Kickback action.
     * @return \Awards\Model\Entity\ApprovalProcessStep
     */
    private function resolveKickbackStep(array $steps, int $currentIndex, string $action): ApprovalProcessStep
    {
        if (str_starts_with($action, ApprovalProcessStep::ACTION_RETURN_STEP_PREFIX)) {
            $targetKey = substr($action, strlen(ApprovalProcessStep::ACTION_RETURN_STEP_PREFIX));
            $targetIndex = $this->findStepIndex($steps, $targetKey);
            if ($targetIndex !== null) {
                return $steps[$targetIndex];
            }
        }

        return $steps[max(0, $currentIndex - 1)];
    }

    /**
     * Update run current-step projection.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step.
     * @param string $status Projection status.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function updateRunStep(
        RecommendationApprovalRun $run,
        ApprovalProcessStep $step,
        string $status,
        ?int $actorId,
    ): void {
        $run->status = $status;
        $run->current_step_key = (string)$step->step_key;
        $run->current_step_label = (string)$step->label;
        $run->modified_by = $actorId;
        $this->fetchTable('Awards.RecommendationApprovalRuns')->saveOrFail($run);
    }

    /**
     * Transition recommendation state for terminal outcomes that still use legacy recommendation state.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param string $targetState Target state.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function transitionRecommendation(
        Recommendation $recommendation,
        string $targetState,
        ?int $actorId,
        ?string $closeReason = null,
    ): void {
        $recommendationsTable = $this->fetchTable('Awards.Recommendations');

        if ($actorId === null) {
            $recommendation->state = $targetState;
            if ($closeReason !== null) {
                $recommendation->close_reason = $closeReason;
            }
            $recommendationsTable->saveOrFail($recommendation);

            return;
        }

        if ((string)$recommendation->state !== $targetState) {
            $transitionData = ['targetState' => $targetState];
            if ($closeReason !== null) {
                $transitionData['close_reason'] = $closeReason;
            }
            $result = $this->transitionService->transition(
                $recommendationsTable,
                (int)$recommendation->id,
                $transitionData,
                $actorId,
            );
            if (!($result['success'] ?? false)) {
                throw new RuntimeException((string)($result['error'] ?? 'Recommendation state transition failed.'));
            }

            return;
        }

        if ($closeReason !== null && (string)$recommendation->close_reason !== $closeReason) {
            $recommendation->close_reason = $closeReason;
            $recommendationsTable->saveOrFail($recommendation);
        }
    }

    /**
     * Resolve the reject comment from workflow resume/action context.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return string|null
     */
    private function resolveRejectionComment(array $context, array $config): ?string
    {
        $comment = $context['resumeData']['comment']
            ?? $context['comment']
            ?? $config['comment']
            ?? null;
        if (!is_string($comment)) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : $comment;
    }

    /**
     * Sort process steps by sequence.
     *
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Steps.
     * @return array<int, \Awards\Model\Entity\ApprovalProcessStep>
     */
    private function orderedSteps(array $steps): array
    {
        usort($steps, static fn($left, $right): int => ((int)$left->sequence) <=> ((int)$right->sequence));

        return array_values($steps);
    }

    /**
     * Find a step index by key.
     *
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Steps.
     * @param string $stepKey Step key.
     * @return int|null
     */
    private function findStepIndex(array $steps, string $stepKey): ?int
    {
        foreach ($steps as $index => $step) {
            if ((string)$step->step_key === $stepKey) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Resolve workflow instance ID from workflow context.
     *
     * @param array<string, mixed> $context Workflow context.
     * @return int
     */
    private function resolveInstanceId(array $context): int
    {
        $instanceId = (int)($context['instanceId'] ?? 0);
        if ($instanceId <= 0) {
            throw new RuntimeException('Workflow instance ID is required.');
        }

        return $instanceId;
    }

    /**
     * Resolve recommendation ID from config or trigger context.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return int
     */
    private function resolveRecommendationId(array $context, array $config): int
    {
        $trigger = $context['trigger'] ?? [];
        $recommendationId = (int)(
            $config['recommendationId']
            ?? $context['recommendationId']
            ?? $trigger['recommendationId']
            ?? 0
        );
        if ($recommendationId <= 0) {
            throw new RuntimeException('Recommendation ID is required.');
        }

        return $recommendationId;
    }

    /**
     * Resolve actor ID from config or trigger context.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return int|null
     */
    private function resolveActorId(array $context, array $config): ?int
    {
        $trigger = $context['trigger'] ?? [];
        $actorId = (int)(
            $config['actorId']
            ?? $context['actorId']
            ?? $context['triggeredBy']
            ?? $trigger['actorId']
            ?? $trigger['requesterId']
            ?? 0
        );

        return $actorId > 0 ? $actorId : null;
    }

    /**
     * Resolve an approval outcome from workflow context or action config.
     *
     * @param array<string, mixed> $context Workflow context.
     * @param array<string, mixed> $config Node config.
     * @return string
     */
    private function resolveApprovalStatus(array $context, array $config): string
    {
        $approvalNodeId = (string)($config['approvalNodeId'] ?? '');
        $approvalNodeContext = $approvalNodeId !== ''
            ? (array)($context['nodes'][$approvalNodeId] ?? [])
            : [];

        $status = (string)(
            $config['approvalStatus']
            ?? $context['approval']['approvalStatus']
            ?? $context['approvalStatus']
            ?? $approvalNodeContext['approvalStatus']
            ?? ''
        );
        if ($status !== '') {
            return $status;
        }

        $decision = (string)(
            $config['decision']
            ?? $context['approval']['decision']
            ?? $context['decision']
            ?? $approvalNodeContext['decision']
            ?? ''
        );

        return match ($decision) {
            'approve', 'approved' => 'approved',
            'reject', 'rejected' => 'rejected',
            default => '',
        };
    }
}
