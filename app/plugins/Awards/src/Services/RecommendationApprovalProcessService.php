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

    public const IN_APPROVAL_STATE = 'In Approval';
    public const CHANGES_REQUESTED_STATE = 'Changes Requested';

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
                $run = $runsTable->newEntity([
                    'recommendation_id' => $recommendation->id,
                    'approval_process_id' => $process->id,
                    'workflow_instance_id' => $instanceId,
                    'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    'current_step_key' => $firstStep->step_key,
                    'current_step_label' => $firstStep->label,
                    'started' => DateTime::now(),
                    'created_by' => $actorId,
                    'modified_by' => $actorId,
                ]);
                if (!$runsTable->save($run)) {
                    return new ServiceResult(false, 'The recommendation approval run could not be saved.');
                }
            }
            $this->transitionRecommendation($recommendation, self::IN_APPROVAL_STATE, $actorId);

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

            $currentStep = $steps[$currentIndex];
            if ($approvalStatus === 'rejected') {
                return $this->handleRejectedStep($run, $recommendation, $steps, $currentIndex, $currentStep, $actorId);
            }

            if ($approvalStatus !== 'approved') {
                return new ServiceResult(false, 'Only approved or rejected approval statuses can advance a process.');
            }

            $nextStep = $steps[$currentIndex + 1] ?? null;
            if ($nextStep) {
                $this->updateRunStep($run, $nextStep, RecommendationApprovalRun::STATUS_IN_PROGRESS, $actorId);
                $this->transitionRecommendation($recommendation, self::IN_APPROVAL_STATE, $actorId);

                return new ServiceResult(true, null, $this->stepOutput($run, $recommendation, $nextStep));
            }

            $run->status = RecommendationApprovalRun::STATUS_APPROVED;
            $run->current_step_key = null;
            $run->current_step_label = null;
            $run->completed = DateTime::now();
            $run->modified_by = $actorId;
            $runsTable->saveOrFail($run);
            $this->transitionRecommendation(
                $recommendation,
                RecommendationBestowalStatePolicyService::HANDOFF_STATE,
                $actorId,
            );

            return new ServiceResult(true, null, [
                'runId' => (int)$run->id,
                'status' => $run->status,
                'completed' => true,
                'targetState' => RecommendationBestowalStatePolicyService::HANDOFF_STATE,
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
        if (!empty($config['eligible_member_ids']) && is_array($config['eligible_member_ids'])) {
            return array_values(array_unique(array_map('intval', $config['eligible_member_ids'])));
        }

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
            $steps = $this->orderedSteps($recommendation->award->approval_process->approval_process_steps ?? []);
            $stepKey = (string)($config['award_approval_step_key'] ?? $run->current_step_key);
            $stepIndex = $this->findStepIndex($steps, $stepKey);
            if ($stepIndex === null) {
                return [];
            }

            return $this->approverIds($steps[$stepIndex], $recommendation);
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
        $approverIds = $this->approverIds($step, $recommendation);
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
            'approvalApproverConfig' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_run_id' => (int)$run->id,
                'award_approval_step_key' => (string)$step->step_key,
                'eligible_member_ids' => $approverIds,
                'retain_read_visibility' => (bool)$step->retain_read_visibility,
                'on_reject' => (string)$step->on_reject,
                'on_request_changes' => (string)$step->on_request_changes,
            ],
        ];
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
     * Handle a rejected approval step according to the step kickback rule.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param array<int, \Awards\Model\Entity\ApprovalProcessStep> $steps Ordered steps.
     * @param int $currentIndex Current step index.
     * @param \Awards\Model\Entity\ApprovalProcessStep $currentStep Current step.
     * @param int|null $actorId Actor ID.
     * @return \App\Services\ServiceResult
     */
    private function handleRejectedStep(
        RecommendationApprovalRun $run,
        Recommendation $recommendation,
        array $steps,
        int $currentIndex,
        ApprovalProcessStep $currentStep,
        ?int $actorId,
    ): ServiceResult {
        if ($currentStep->on_reject === ApprovalProcessStep::ACTION_CLOSE) {
            $run->status = RecommendationApprovalRun::STATUS_CLOSED;
            $run->completed = DateTime::now();
            $run->modified_by = $actorId;
            $this->fetchTable('Awards.RecommendationApprovalRuns')->saveOrFail($run);
            $this->transitionRecommendation(
                $recommendation,
                RecommendationBestowalStatePolicyService::NO_ACTION_STATE,
                $actorId,
            );

            return new ServiceResult(true, null, [
                'runId' => (int)$run->id,
                'status' => $run->status,
                'closed' => true,
            ]);
        }

        $targetStep = $this->resolveKickbackStep($steps, $currentIndex, (string)$currentStep->on_reject);
        $this->updateRunStep($run, $targetStep, RecommendationApprovalRun::STATUS_CHANGES_REQUESTED, $actorId);
        $this->transitionRecommendation($recommendation, self::CHANGES_REQUESTED_STATE, $actorId);

        return new ServiceResult(true, null, $this->stepOutput($run, $recommendation, $targetStep));
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
     * Transition recommendation state after terminal approval process outcomes.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param string $targetState Target state.
     * @param int|null $actorId Actor ID.
     * @return void
     */
    private function transitionRecommendation(Recommendation $recommendation, string $targetState, ?int $actorId): void
    {
        if ((string)$recommendation->state === $targetState) {
            return;
        }

        if ($actorId === null) {
            $recommendation->state = $targetState;
            $this->fetchTable('Awards.Recommendations')->saveOrFail($recommendation);

            return;
        }

        $result = $this->transitionService->transition(
            $this->fetchTable('Awards.Recommendations'),
            (int)$recommendation->id,
            ['targetState' => $targetState],
            $actorId,
        );
        if (!($result['success'] ?? false)) {
            throw new RuntimeException((string)($result['error'] ?? 'Recommendation state transition failed.'));
        }
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
