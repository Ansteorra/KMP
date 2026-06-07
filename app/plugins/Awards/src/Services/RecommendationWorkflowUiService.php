<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Builds recommendation approval workflow context for recommendation screens.
 */
class RecommendationWorkflowUiService
{
    use LocatorAwareTrait;

    private RecommendationApprovalWorkflowLifecycleService $lifecycle;

    /**
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $lifecycle Lifecycle service.
     */
    public function __construct(?RecommendationApprovalWorkflowLifecycleService $lifecycle = null)
    {
        $this->lifecycle = $lifecycle ?? new RecommendationApprovalWorkflowLifecycleService();
    }

    /**
     * Build workflow display and action context for a recommendation.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param \App\KMP\KmpIdentityInterface|null $identity Current identity.
     * @return array<string, mixed>
     */
    public function buildContext(Recommendation $recommendation, ?KmpIdentityInterface $identity): array
    {
        $recommendationId = (int)$recommendation->id;
        $scopeIds = $this->lifecycle->approvalScopeRecommendationIds($recommendationId);
        $runs = $this->loadRuns($scopeIds);
        $latestRun = $runs[0] ?? null;
        $activeRuns = array_values(array_filter(
            $runs,
            static fn(RecommendationApprovalRun $run): bool => in_array(
                $run->status,
                RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                true,
            ),
        ));
        $pendingApproval = $this->pendingApprovalForIdentity($activeRuns, $identity);

        return [
            'scopeRecommendationIds' => $scopeIds,
            'runs' => $this->formatRuns($runs),
            'summary' => $this->buildSummary($latestRun),
            'latestRun' => $latestRun,
            'activeRun' => $activeRuns[0] ?? null,
            'pendingApproval' => $pendingApproval,
            'decisionOptions' => $pendingApproval
                ? WorkflowApprovalDecisionOptions::normalizeOptions($pendingApproval->approver_config ?? [])
                : [],
            'canDecide' => $pendingApproval !== null,
            'canStartWorkflow' => $this->canStartWorkflow($recommendation, $latestRun, $activeRuns),
        ];
    }

    /**
     * Return the current user's pending approval for a recommendation, if any.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param \App\KMP\KmpIdentityInterface|null $identity Current identity.
     * @return \App\Model\Entity\WorkflowApproval|null
     */
    public function pendingApprovalForRecommendation(
        Recommendation $recommendation,
        ?KmpIdentityInterface $identity,
    ): ?WorkflowApproval {
        $scopeIds = $this->lifecycle->approvalScopeRecommendationIds((int)$recommendation->id);
        $activeRuns = array_values(array_filter(
            $this->loadRuns($scopeIds),
            static fn(RecommendationApprovalRun $run): bool => in_array(
                $run->status,
                RecommendationApprovalWorkflowLifecycleService::ACTIVE_STATUSES,
                true,
            ),
        ));

        return $this->pendingApprovalForIdentity($activeRuns, $identity);
    }

    /**
     * Determine whether a new approval workflow can be started.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @return bool
     */
    public function canStartApprovalWorkflow(Recommendation $recommendation): bool
    {
        $latestRun = $this->lifecycle->findLatestRun((int)$recommendation->id);
        $activeRuns = $this->lifecycle->findActiveRuns([(int)$recommendation->id]);

        return $this->canStartWorkflow($recommendation, $latestRun, $activeRuns);
    }

    /**
     * @param array<int> $scopeIds Recommendation scope IDs.
     * @return array<int, \Awards\Model\Entity\RecommendationApprovalRun>
     */
    private function loadRuns(array $scopeIds): array
    {
        if ($scopeIds === []) {
            return [];
        }

        return $this->fetchTable('Awards.RecommendationApprovalRuns')->find()
            ->contain([
                'WorkflowInstances' => [
                    'WorkflowDefinitions',
                    'WorkflowApprovals' => [
                        'CurrentApprover',
                        'WorkflowApprovalResponses' => ['Members'],
                    ],
                ],
                'ApprovalProcesses' => [
                    'ApprovalProcessSteps',
                ],
                'RehydratedFromRuns',
            ])
            ->where([
                'RecommendationApprovalRuns.recommendation_id IN' => $scopeIds,
                'RecommendationApprovalRuns.deleted IS' => null,
            ])
            ->orderByDesc('RecommendationApprovalRuns.completed')
            ->orderByDesc('RecommendationApprovalRuns.started')
            ->orderByDesc('RecommendationApprovalRuns.id')
            ->all()
            ->toArray();
    }

    /**
     * @param array<int, \Awards\Model\Entity\RecommendationApprovalRun> $activeRuns Active runs.
     * @param \App\KMP\KmpIdentityInterface|null $identity Current identity.
     * @return \App\Model\Entity\WorkflowApproval|null
     */
    private function pendingApprovalForIdentity(array $activeRuns, ?KmpIdentityInterface $identity): ?WorkflowApproval
    {
        $memberId = (int)($identity?->getAsMember()->id ?? 0);
        if ($memberId <= 0 || $activeRuns === []) {
            return null;
        }

        $workflowInstanceIds = array_values(array_unique(array_filter(array_map(
            static fn(RecommendationApprovalRun $run): int => (int)$run->workflow_instance_id,
            $activeRuns,
        ))));
        if ($workflowInstanceIds === []) {
            return null;
        }

        $approvals = $this->fetchTable('WorkflowApprovals')->find()
            ->contain(['CurrentApprover'])
            ->where([
                'WorkflowApprovals.workflow_instance_id IN' => $workflowInstanceIds,
                'WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->orderByDesc('WorkflowApprovals.modified')
            ->all();

        $approvalManager = new DefaultWorkflowApprovalManager();
        foreach ($approvals as $approval) {
            $eligibleIds = array_map(
                static fn($member): int => (int)$member->id,
                $approvalManager->getEligibleApprovers((int)$approval->id),
            );
            if (in_array($memberId, $eligibleIds, true)) {
                return $approval;
            }
        }

        return null;
    }

    /**
     * @param array<int, \Awards\Model\Entity\RecommendationApprovalRun> $runs Runs.
     * @return array<int, array<string, mixed>>
     */
    private function formatRuns(array $runs): array
    {
        return array_map(function (RecommendationApprovalRun $run): array {
            $instance = $run->workflow_instance ?? null;
            $approvals = $instance?->workflow_approvals ?? [];

            return [
                'run' => $run,
                'workflowInstance' => $instance,
                'workflowDefinition' => $instance?->workflow_definition ?? null,
                'summary' => $this->buildRunSummary($run),
                'approvals' => $approvals,
            ];
        }, $runs);
    }

    /**
     * Build high-level workflow progress for the current recommendation.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun|null $latestRun Latest run.
     * @return array<string, mixed>
     */
    private function buildSummary(?RecommendationApprovalRun $latestRun): array
    {
        if ($latestRun === null) {
            return [
                'hasRun' => false,
                'totalSteps' => 0,
                'completedSteps' => 0,
                'pendingSteps' => 0,
                'pendingResponses' => 0,
                'progressPercent' => 0,
                'nextSteps' => [],
                'upcomingSteps' => [],
                'completedStepLabels' => [],
            ];
        }

        return ['hasRun' => true] + $this->buildRunSummary($latestRun);
    }

    /**
     * Build progress details for a specific approval run.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @return array<string, mixed>
     */
    private function buildRunSummary(RecommendationApprovalRun $run): array
    {
        $approvals = $run->workflow_instance?->workflow_approvals ?? [];
        $totalSteps = count($approvals);
        $completedSteps = 0;
        $pendingSteps = 0;
        $pendingResponses = 0;
        $nextSteps = [];
        $upcomingSteps = [];
        $completedStepLabels = [];
        $processSteps = $run->approval_process->approval_process_steps ?? [];

        if ($processSteps !== []) {
            return $this->buildProcessStepSummary($run, $approvals, $processSteps);
        }

        foreach ($approvals as $approval) {
            if (!$approval instanceof WorkflowApproval) {
                continue;
            }

            $label = $this->approvalStepLabel($approval, $run);
            if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                $pendingSteps++;
                $pendingNeeded = max(0, (int)$approval->required_count - (int)$approval->approved_count);
                $pendingResponses += $pendingNeeded;
                $nextSteps[] = [
                    'label' => $label,
                    'approved' => (int)$approval->approved_count,
                    'rejected' => (int)$approval->rejected_count,
                    'required' => (int)$approval->required_count,
                    'pendingResponses' => $pendingNeeded,
                    'currentApprover' => $approval->current_approver->sca_name
                        ?? $approval->current_approver->member_number
                        ?? null,
                ];

                continue;
            }

            if ($approval->isResolved()) {
                $completedSteps++;
                $completedStepLabels[] = [
                    'label' => $label,
                    'status' => (string)$approval->status,
                    'responses' => $this->formatApprovalResponses($approval),
                ];
            }
        }

        $progressPercent = $totalSteps > 0 ? (int)round($completedSteps / $totalSteps * 100) : 0;

        return [
            'totalSteps' => $totalSteps,
            'completedSteps' => $completedSteps,
            'pendingSteps' => $pendingSteps,
            'pendingResponses' => $pendingResponses,
            'progressPercent' => $progressPercent,
            'nextSteps' => $nextSteps,
            'upcomingSteps' => $upcomingSteps,
            'completedStepLabels' => $completedStepLabels,
        ];
    }

    /**
     * Build progress from Awards approval-process steps, including future steps not yet materialized.
     *
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param array<int, mixed> $approvals Materialized workflow approvals.
     * @param array<int, mixed> $processSteps Configured approval-process steps.
     * @return array<string, mixed>
     */
    private function buildProcessStepSummary(
        RecommendationApprovalRun $run,
        array $approvals,
        array $processSteps,
    ): array {
        $approvalsByStepKey = $this->approvalsByProcessStepKey($approvals);
        $currentSequence = $this->currentStepSequence($processSteps, (string)$run->current_step_key);
        $isTerminalApproved = in_array($run->status, [
            RecommendationApprovalRun::STATUS_APPROVED,
            RecommendationApprovalRun::STATUS_CONSUMED,
        ], true);
        $isTerminalStopped = in_array($run->status, [
            RecommendationApprovalRun::STATUS_CLOSED,
            RecommendationApprovalRun::STATUS_CANCELLED,
        ], true);

        $completedSteps = 0;
        $pendingSteps = 0;
        $pendingResponses = 0;
        $nextSteps = [];
        $upcomingSteps = [];
        $completedStepLabels = [];

        foreach ($processSteps as $step) {
            $stepKey = (string)$step->step_key;
            $approval = $approvalsByStepKey[$stepKey] ?? null;
            $label = (string)$step->label;
            $sequence = (int)$step->sequence;

            if ($approval instanceof WorkflowApproval && $approval->isResolved()) {
                $completedSteps++;
                $completedStepLabels[] = [
                    'label' => $label,
                    'status' => (string)$approval->status,
                    'responses' => $this->formatApprovalResponses($approval),
                ];

                continue;
            }

            if ($isTerminalApproved || ($currentSequence !== null && $sequence < $currentSequence)) {
                $completedSteps++;
                $completedStepLabels[] = [
                    'label' => $label,
                    'status' => WorkflowApproval::STATUS_APPROVED,
                    'responses' => $approval instanceof WorkflowApproval
                        ? $this->formatApprovalResponses($approval)
                        : [],
                ];

                continue;
            }

            if ($isTerminalStopped) {
                $completedStepLabels[] = [
                    'label' => $label,
                    'status' => (string)$run->status,
                    'responses' => $approval instanceof WorkflowApproval
                        ? $this->formatApprovalResponses($approval)
                        : [],
                ];

                continue;
            }

            if ($stepKey === (string)$run->current_step_key || $approval instanceof WorkflowApproval) {
                $required = $approval instanceof WorkflowApproval ? (int)$approval->required_count : 1;
                $approved = $approval instanceof WorkflowApproval ? (int)$approval->approved_count : 0;
                $rejected = $approval instanceof WorkflowApproval ? (int)$approval->rejected_count : 0;
                $pendingNeeded = max(0, $required - $approved);
                $pendingSteps++;
                $pendingResponses += $pendingNeeded;
                $nextSteps[] = [
                    'label' => $label,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'required' => $required,
                    'pendingResponses' => $pendingNeeded,
                    'currentApprover' => $approval?->current_approver?->sca_name
                        ?? $approval?->current_approver?->member_number
                        ?? null,
                    'state' => 'current',
                ];

                continue;
            }

            $upcomingSteps[] = [
                'label' => $label,
                'sequence' => $sequence,
                'state' => 'upcoming',
            ];
        }

        $totalSteps = count($processSteps);
        $progressPercent = $totalSteps > 0 ? (int)round($completedSteps / $totalSteps * 100) : 0;

        return [
            'totalSteps' => $totalSteps,
            'completedSteps' => $completedSteps,
            'pendingSteps' => $pendingSteps,
            'pendingResponses' => $pendingResponses,
            'progressPercent' => $progressPercent,
            'nextSteps' => $nextSteps,
            'upcomingSteps' => $upcomingSteps,
            'completedStepLabels' => $completedStepLabels,
        ];
    }

    /**
     * Index workflow approvals by their Awards approval-process step key.
     *
     * @param array<int, mixed> $approvals Materialized approvals.
     * @return array<string, \App\Model\Entity\WorkflowApproval>
     */
    private function approvalsByProcessStepKey(array $approvals): array
    {
        $indexed = [];
        foreach ($approvals as $approval) {
            if (!$approval instanceof WorkflowApproval) {
                continue;
            }

            $stepKey = $approval->approver_config['award_approval_step_key'] ?? null;
            if (is_string($stepKey) && $stepKey !== '') {
                $indexed[$stepKey] = $approval;
            }
        }

        return $indexed;
    }

    /**
     * Find the configured sequence for the current approval process step.
     *
     * @param array<int, mixed> $processSteps Configured process steps.
     * @param string $currentStepKey Current step key.
     * @return int|null
     */
    private function currentStepSequence(array $processSteps, string $currentStepKey): ?int
    {
        foreach ($processSteps as $step) {
            if ((string)$step->step_key === $currentStepKey) {
                return (int)$step->sequence;
            }
        }

        return null;
    }

    /**
     * Return a human-friendly label for an approval gate.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval gate.
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @return string
     */
    private function approvalStepLabel(WorkflowApproval $approval, RecommendationApprovalRun $run): string
    {
        if ($run->current_step_key === $approval->node_id && !empty($run->current_step_label)) {
            return (string)$run->current_step_label;
        }

        $config = $approval->approver_config ?? [];
        if (!empty($config['label'])) {
            return (string)$config['label'];
        }
        if (!empty($config['step_label'])) {
            return (string)$config['step_label'];
        }

        return (string)$approval->node_id;
    }

    /**
     * Format approval responses for concise progress summaries.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval gate.
     * @return array<int, array<string, string>>
     */
    private function formatApprovalResponses(WorkflowApproval $approval): array
    {
        $responses = $approval->workflow_approval_responses ?? [];

        return array_map(
            function (WorkflowApprovalResponse $response) use ($approval): array {
                return [
                    'member' => (string)($response->member->sca_name ?? $response->member_id),
                    'decision' => WorkflowApprovalDecisionOptions::labelForDecision(
                        (string)$response->decision,
                        $approval->approver_config ?? [],
                    ),
                ];
            },
            $responses,
        );
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @param \Awards\Model\Entity\RecommendationApprovalRun|null $latestRun Latest run.
     * @param array<int, \Awards\Model\Entity\RecommendationApprovalRun> $activeRuns Active runs.
     * @return bool
     */
    private function canStartWorkflow(
        Recommendation $recommendation,
        ?RecommendationApprovalRun $latestRun,
        array $activeRuns,
    ): bool {
        if ($recommendation->isLockedByBestowal() || $activeRuns !== []) {
            return false;
        }

        if ($latestRun === null) {
            return true;
        }

        return in_array($latestRun->status, [
            RecommendationApprovalRun::STATUS_CLOSED,
            RecommendationApprovalRun::STATUS_CANCELLED,
        ], true);
    }
}
