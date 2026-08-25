<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\KMP\GridColumns\ApprovalsGridColumns;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use Cake\TestSuite\TestCase;

/**
 * Presentation tests for computed approval grid fields.
 */
class ApprovalsControllerPresentationTest extends TestCase
{
    public function testFeedbackApprovalUsesFeedbackPendingLabel(): void
    {
        $approval = new WorkflowApproval([
            'status' => WorkflowApproval::STATUS_PENDING,
            'approved_count' => 0,
            'required_count' => 1,
            'approver_config' => [
                'feedback_response' => true,
                'pending_status_label' => 'Feedback requested',
            ],
        ]);

        $this->assertTrue(ApprovalsGridColumns::isFeedbackResponse($approval->approver_config));
        $this->assertSame('Feedback requested', ApprovalsGridColumns::getPendingStatusLabel($approval));
    }

    public function testRegularApprovalKeepsApprovalProgressLabel(): void
    {
        $approval = new WorkflowApproval([
            'status' => WorkflowApproval::STATUS_PENDING,
            'approved_count' => 0,
            'required_count' => 1,
            'approver_config' => [],
        ]);

        $this->assertFalse(ApprovalsGridColumns::isFeedbackResponse($approval->approver_config));
        $this->assertSame('Pending (0/1)', ApprovalsGridColumns::getPendingStatusLabel($approval));
    }

    public function testProcessUpdateCancellationUsesPlainLanguageResetPresentation(): void
    {
        $approval = new WorkflowApproval([
            'status' => WorkflowApproval::STATUS_CANCELLED,
            'workflow_instance' => new WorkflowInstance([
                'error_info' => ['cancellation_reason' => 'approval_process_restarted'],
            ]),
        ]);

        $this->assertSame('Reset — approval process updated', ApprovalsGridColumns::getStatusLabel($approval));
        $this->assertSame(
            'This approval was cancelled because the approval process changed. ' .
            'A new approval was started using the current process, so earlier decisions do not count toward it.',
            ApprovalsGridColumns::getStatusExplanation($approval),
        );
    }

    public function testOtherCancellationReasonsRemainGeneric(): void
    {
        $approval = new WorkflowApproval([
            'status' => WorkflowApproval::STATUS_CANCELLED,
            'workflow_instance' => new WorkflowInstance([
                'error_info' => ['cancellation_reason' => 'recommendation_deleted'],
            ]),
        ]);

        $this->assertSame('Cancelled', ApprovalsGridColumns::getStatusLabel($approval));
        $this->assertNull(ApprovalsGridColumns::getStatusExplanation($approval));
    }
}
