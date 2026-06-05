<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;

class ApprovalProcessStepsTableTest extends BaseTestCase
{
    public function testValidationRequiresTypedSourceForRoleStep(): void
    {
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');

        $step = $steps->newEntity([
            'approval_process_id' => 1,
            'step_key' => 'local',
            'label' => 'Local approval',
            'sequence' => 1,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_ROLE,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ]);

        $this->assertNotEmpty($step->getError('approver_source_id'));
    }

    public function testValidationRequiresDynamicSourceKeyForDynamicStep(): void
    {
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');

        $step = $steps->newEntity([
            'approval_process_id' => 1,
            'step_key' => 'polling',
            'label' => 'Polling',
            'sequence' => 1,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_DYNAMIC,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ]);

        $this->assertNotEmpty($step->getError('approver_source_key'));
    }

    public function testValidationRequiresAncestorTypeForAncestorBranchMode(): void
    {
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');

        $step = $steps->newEntity([
            'approval_process_id' => 1,
            'step_key' => 'crown',
            'label' => 'Crown approval',
            'sequence' => 1,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            'approver_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE,
            'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ]);

        $this->assertNotEmpty($step->getError('branch_type'));
    }
}
