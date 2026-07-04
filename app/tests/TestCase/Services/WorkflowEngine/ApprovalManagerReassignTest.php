<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class ApprovalManagerReassignTest extends BaseTestCase
{
    private DefaultWorkflowApprovalManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new DefaultWorkflowApprovalManager();
    }

    public function testReassignApprovalUpdatesCurrentApproverAndAuditConfig(): void
    {
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'member_id' => self::ADMIN_MEMBER_ID,
        ]);

        $result = $this->manager->reassignApproval(
            $approvalId,
            self::TEST_MEMBER_AGATHA_ID,
            self::ADMIN_MEMBER_ID,
            'Out of office',
        );

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $approval = TableRegistry::getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$approval->current_approver_id);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$approval->approver_config['current_approver_id']);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$approval->approver_config['reassigned_by']);
        $this->assertSame('Out of office', $approval->approver_config['reassignment_reason']);
    }

    public function testReassignApprovalRejectsResolvedApproval(): void
    {
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'member_id' => self::ADMIN_MEMBER_ID,
        ], WorkflowApproval::APPROVER_TYPE_MEMBER, WorkflowApproval::STATUS_APPROVED);

        $result = $this->manager->reassignApproval(
            $approvalId,
            self::TEST_MEMBER_AGATHA_ID,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Only pending approvals can be reassigned.', $result->getError());
    }

    public function testGetEligibleApproversReturnsConfiguredMember(): void
    {
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'member_id' => self::ADMIN_MEMBER_ID,
        ]);

        $members = $this->manager->getEligibleApprovers($approvalId);

        $this->assertCount(1, $members);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$members[0]->id);
    }

    public function testGetNextApproverCandidatesExcludesPriorResponders(): void
    {
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'eligible_member_ids' => [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_AGATHA_ID],
            'current_approver_id' => self::ADMIN_MEMBER_ID,
        ], WorkflowApproval::APPROVER_TYPE_DYNAMIC);
        $this->createApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);

        $candidates = $this->manager->getNextApproverCandidates($approvalId, self::ADMIN_MEMBER_ID);

        $this->assertContains(self::TEST_MEMBER_AGATHA_ID, $candidates);
        $this->assertNotContains(self::ADMIN_MEMBER_ID, $candidates);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createWorkflowContext(): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Approval Manager Reassign Test',
            'slug' => 'approval-manager-reassign-test-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $defTable->saveOrFail($def);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'approval_node' => ['type' => 'approval', 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $versionsTable->saveOrFail($version);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
            'context' => ['trigger' => ['memberId' => self::TEST_MEMBER_BRYCE_ID]],
            'active_nodes' => ['approval_node'],
        ]);
        $instancesTable->saveOrFail($instance);

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'node_type' => 'approval',
            'status' => 'waiting',
        ]);
        $logsTable->saveOrFail($log);

        return [(int)$instance->id, (int)$log->id];
    }

    private function createApproval(
        int $instanceId,
        int $executionLogId,
        array $approverConfig,
        string $approverType = WorkflowApproval::APPROVER_TYPE_MEMBER,
        string $status = WorkflowApproval::STATUS_PENDING,
    ): int {
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval_node',
            'execution_log_id' => $executionLogId,
            'approver_type' => $approverType,
            'approver_config' => $approverConfig,
            'current_approver_id' => $approverConfig['current_approver_id'] ?? $approverConfig['member_id'] ?? null,
            'request_title' => 'Approval Manager Reassign Test',
            'required_count' => 1,
            'approved_count' => $status === WorkflowApproval::STATUS_APPROVED ? 1 : 0,
            'rejected_count' => 0,
            'status' => $status,
            'allow_parallel' => true,
            'version' => 1,
        ]);
        $approvalsTable->saveOrFail($approval);

        return (int)$approval->id;
    }

    private function createApprovalResponse(int $approvalId, int $memberId): void
    {
        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $response = $responsesTable->newEntity([
            'workflow_approval_id' => $approvalId,
            'member_id' => $memberId,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'responded_at' => DateTime::now(),
        ]);
        $responsesTable->saveOrFail($response);
    }
}
