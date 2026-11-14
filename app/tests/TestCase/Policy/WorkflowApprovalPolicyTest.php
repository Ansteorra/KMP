<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Model\Entity\ServicePrincipal;
use App\Model\Entity\WorkflowApproval;
use App\Policy\WorkflowApprovalPolicy;
use App\Test\TestCase\BaseTestCase;

class WorkflowApprovalPolicyTest extends BaseTestCase
{
    public function testServicePrincipalIdCannotBeTreatedAsAnAssignedMemberId(): void
    {
        $principal = new ServicePrincipal(['id' => self::TEST_MEMBER_AGATHA_ID, 'name' => 'Synthetic principal']);
        $approval = new WorkflowApproval(['id' => 1, 'approver_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID]]);
        $policy = new WorkflowApprovalPolicy();

        $this->assertFalse($policy->canView($principal, $approval));
        $this->assertFalse($policy->canEligibleApprovers($principal, $approval));
        $this->assertFalse($policy->canReassignApproval($principal, $approval));
    }

    public function testUnsavedApprovalDoesNotGrantReadAccess(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $policy = new WorkflowApprovalPolicy();

        $this->assertFalse($policy->canView($member, new WorkflowApproval()));
        $this->assertFalse($policy->canEligibleApprovers($member, new WorkflowApproval()));
    }
}
