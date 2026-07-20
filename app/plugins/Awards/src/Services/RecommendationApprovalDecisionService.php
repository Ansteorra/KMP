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
    /**
     * @param \App\Services\WorkflowEngine\WorkflowApprovalManagerInterface $approvalManager Approval manager
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $workflowEngine Workflow engine
     */
    public function __construct(
        private WorkflowApprovalManagerInterface $approvalManager,
        private WorkflowEngineInterface $workflowEngine,
    ) {
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
        $result = $this->approvalManager->recordResponse(
            (int)$approval->id,
            $memberId,
            $decision,
            $comment,
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
            if ($bestowalGatheringId !== null) {
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
            if ($bestowalGatheringId !== null) {
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
}
