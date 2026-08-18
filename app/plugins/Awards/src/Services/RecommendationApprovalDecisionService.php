<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowApprovalManagerInterface;
use App\Services\WorkflowEngine\WorkflowEngineInterface;

class RecommendationApprovalDecisionService
{
    private const BESTOWAL_GATHERING_WORKFLOW_SLUGS = [
        'awards-recommendation-submitted',
        'awards-existing-recommendation-approval',
    ];

    private RecommendationApprovalProcessService $approvalProcessService;

    /**
     * @param \App\Services\WorkflowEngine\WorkflowApprovalManagerInterface $approvalManager Approval manager
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $workflowEngine Workflow engine
     * @param \Awards\Services\RecommendationApprovalProcessService|null $approvalProcessService Process state reader
     */
    public function __construct(
        private WorkflowApprovalManagerInterface $approvalManager,
        private WorkflowEngineInterface $workflowEngine,
        ?RecommendationApprovalProcessService $approvalProcessService = null,
    ) {
        $this->approvalProcessService = $approvalProcessService ?? new RecommendationApprovalProcessService();
    }

    /**
     * Validate a submitted approval decision.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity
     * @param string $decision Submitted decision
     * @param string $comment Submitted comment text
     * @return string|null Validation message
     */
    public function validateDecision(WorkflowApproval $approval, string $decision, string $comment): ?string
    {
        return self::validateApprovalDecision($approval, $decision, $comment);
    }

    /**
     * Static validation helper for controller paths that have not built service dependencies yet.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity
     * @param string $decision Submitted decision
     * @param string $comment Submitted comment text
     * @return string|null Validation message
     */
    public static function validateApprovalDecision(
        WorkflowApproval $approval,
        string $decision,
        string $comment,
    ): ?string {
        $approverConfig = is_array($approval->approver_config) ? $approval->approver_config : [];
        $requiresComment = $decision === WorkflowApprovalResponse::DECISION_REJECT
            || !empty($approverConfig['requires_comment']);

        if ($requiresComment && $comment === '') {
            return __('A comment is required for this approval decision.');
        }

        if (!in_array($decision, WorkflowApprovalDecisionOptions::allowedValues($approverConfig), true)) {
            return __('Invalid approval decision.');
        }

        return null;
    }

    /**
     * Record a recommendation approval decision and drive follow-up workflow work.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity
     * @param int $memberId Responding member ID
     * @param string $decision Decision value
     * @param string|null $comment Optional comment
     * @param int|null $bestowalGatheringId Optional gathering selected by the final approval step
     * @return \App\Services\ServiceResult
     */
    public function decide(
        WorkflowApproval $approval,
        int $memberId,
        string $decision,
        ?string $comment,
        ?int $bestowalGatheringId = null,
    ): ServiceResult {
        $persistBestowalGathering = $decision === WorkflowApprovalResponse::DECISION_APPROVE
            && $bestowalGatheringId !== null
            && $this->requiresFinalStepGathering($approval);
        $result = $this->approvalManager->recordResponse(
            (int)$approval->id,
            $memberId,
            $decision,
            $comment,
            null,
            $persistBestowalGathering
                ? ['bestowal_gathering_id' => $bestowalGatheringId]
                : [],
        );

        if (!$result->isSuccess() || !$result->getData()) {
            return $result;
        }

        $data = $result->getData();
        if (
            in_array($data['approvalStatus'] ?? '', [
                WorkflowApproval::STATUS_APPROVED,
                WorkflowApproval::STATUS_REJECTED,
            ], true)
        ) {
            $outputPort = $data['approvalStatus'] === WorkflowApproval::STATUS_APPROVED ? 'approved' : 'rejected';
            $resumeData = [
                'approval' => $data,
                'approverId' => $memberId,
                'decision' => $decision,
                'comment' => $comment,
            ];
            if ($persistBestowalGathering) {
                $resumeData['bestowalGatheringId'] = $bestowalGatheringId;
            }

            $resume = $this->workflowEngine->resumeWorkflow(
                (int)$data['instanceId'],
                (string)$data['nodeId'],
                $outputPort,
                $resumeData,
            );
            if (!$resume->isSuccess()) {
                return new ServiceResult(
                    false,
                    $resume->getError() ?? __('The workflow could not be advanced.'),
                );
            }
        } elseif (!empty($data['needsMore'])) {
            $intermediateData = [
                'approverId' => $memberId,
                'decision' => $decision,
                'comment' => $comment,
                'nextApproverId' => $data['nextApproverId'] ?? null,
            ];
            if ($persistBestowalGathering) {
                $intermediateData['bestowalGatheringId'] = $bestowalGatheringId;
            }

            $intermediate = $this->workflowEngine->fireIntermediateApprovalActions(
                (int)$data['instanceId'],
                (string)$data['nodeId'],
                $intermediateData,
            );
            if (!$intermediate->isSuccess()) {
                return new ServiceResult(
                    false,
                    $intermediate->getError() ?? __('Intermediate approval actions could not be completed.'),
                );
            }
        }

        return $result;
    }

    /**
     * Return whether this gate owns the required gathering selection for the final approval step.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Approval being answered.
     * @return bool
     */
    private function requiresFinalStepGathering(WorkflowApproval $approval): bool
    {
        $config = is_array($approval->approver_config) ? $approval->approver_config : [];
        $requiresGathering = !empty($config['requires_bestowal_gathering'])
            || !empty($config['requiresBestowalGathering']);
        $workflowSlug = (string)($approval->workflow_instance?->workflow_definition?->slug ?? '');
        $isAwardRecommendationWorkflow = in_array(
            $workflowSlug,
            self::BESTOWAL_GATHERING_WORKFLOW_SLUGS,
            true,
        );
        if (
            !$isAwardRecommendationWorkflow
            && empty($config['award_approval_run_id'])
            && empty($config['award_approval_step_key'])
            && !array_key_exists('award_approval_is_final_step', $config)
        ) {
            return $requiresGathering;
        }

        $finalStepState = $this->approvalProcessService->isFinalApprovalStep($approval, $config);
        if ($finalStepState === false) {
            return false;
        }

        if ($requiresGathering) {
            return true;
        }

        return $finalStepState === true && $isAwardRecommendationWorkflow;
    }
}
