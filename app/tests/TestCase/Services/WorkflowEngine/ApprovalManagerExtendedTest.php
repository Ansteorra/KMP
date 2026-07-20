<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

/**
 * Extended tests for DefaultWorkflowApprovalManager: serial pick-next,
 * parallel approval, field defaults, and edge cases.
 */
class ApprovalManagerExtendedTest extends BaseTestCase
{
    private DefaultWorkflowApprovalManager $manager;
    private $approvalsTable;
    private $responsesTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new DefaultWorkflowApprovalManager();
        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $this->responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
    }

    /**
     * Helper: create workflow context objects needed for approval testing.
     */
    private function createWorkflowContext(): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Approval Extended ' . uniqid(),
            'slug' => 'approval-ext-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $defTable->saveOrFail($def);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => ['nodes' => [
                'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
                'end1' => ['type' => 'end', 'outputs' => []],
            ]],
            'status' => 'published',
        ]);
        $versionsTable->saveOrFail($version);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
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

        return [$def->id, $instance->id, $log->id];
    }

    /**
     * Helper: create an approval gate.
     */
    private function createApproval(int $instanceId, int $logId, array $overrides = []): int
    {
        $config = array_merge([
            'approverType' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
            'requiredCount' => 1,
        ], $overrides);

        $result = $this->manager->createApproval($instanceId, 'approval_node', $logId, $config);
        $this->assertTrue($result->isSuccess(), 'Failed to create approval: ' . ($result->getError() ?? ''));

        return $result->data['approvalId'];
    }

    // =====================================================
    // createApproval() field defaults
    // =====================================================

    public function testCreateApprovalDefaultsToPermissionType(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();

        $result = $this->manager->createApproval($instanceId, 'node1', $logId, [
            'approverConfig' => ['permission' => 'can_approve'],
        ]);
        $this->assertTrue($result->isSuccess());

        $approval = $this->approvalsTable->get($result->data['approvalId']);
        $this->assertEquals(WorkflowApproval::APPROVER_TYPE_PERMISSION, $approval->approver_type);
    }

    public function testCreateApprovalDefaultsRequiredCountToOne(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();

        $result = $this->manager->createApproval($instanceId, 'node1', $logId, [
            'approverType' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
        ]);
        $this->assertTrue($result->isSuccess());

        $approval = $this->approvalsTable->get($result->data['approvalId']);
        $this->assertEquals(1, $approval->required_count);
    }

    public function testCreateApprovalSetsZeroCounts(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals(0, $approval->approved_count);
        $this->assertEquals(0, $approval->rejected_count);
    }

    public function testCreateApprovalSetsPendingStatus(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals(WorkflowApproval::STATUS_PENDING, $approval->status);
    }

    public function testCreateApprovalStoresNodeId(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals('approval_node', $approval->node_id);
    }

    public function testCreateApprovalWithNullDeadline(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertNull($approval->deadline);
    }

    public function testCreateApprovalWithCustomRequiredCount(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 5,
        ]);

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals(5, $approval->required_count);
    }

    // =====================================================
    // recordResponse() — approve flow
    // =====================================================

    public function testApproveFlowReturnsInstanceAndNodeIds(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $result = $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($instanceId, $result->data['instanceId']);
        $this->assertEquals('approval_node', $result->data['nodeId']);
    }

    public function testApproveFlowIncrementsApprovedCount(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE
        );

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals(1, $approval->approved_count);
    }

    // =====================================================
    // recordResponse() — reject flow
    // =====================================================

    public function testRejectFlowIncrementsRejectedCount(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_REJECT
        );

        $approval = $this->approvalsTable->get($approvalId);
        $this->assertEquals(1, $approval->rejected_count);
    }

    public function testRejectFlowSetsRejectedStatus(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
        ]);

        $result = $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_REJECT
        );

        $this->assertEquals(WorkflowApproval::STATUS_REJECTED, $result->data['approvalStatus']);
    }

    // =====================================================
    // recordResponse() — serial pick-next
    // =====================================================

    public function testSerialPickNextStaysPendingAfterApproval(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $result = $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            'Looks good',
            self::TEST_MEMBER_AGATHA_ID
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('pending', $result->data['approvalStatus']);
        $this->assertTrue($result->data['needsMore']);
        $this->assertEquals(self::TEST_MEMBER_AGATHA_ID, $result->data['nextApproverId']);
    }

    public function testSerialPickNextUpdatesCurrentApprover(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            null,
            self::TEST_MEMBER_AGATHA_ID
        );

        $approval = $this->approvalsTable->get($approvalId);
        $config = $approval->approver_config;
        $this->assertEquals(self::TEST_MEMBER_AGATHA_ID, $config['current_approver_id']);
    }

    public function testSerialPickNextAppendsToChain(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            null,
            self::TEST_MEMBER_AGATHA_ID
        );

        $approval = $this->approvalsTable->get($approvalId);
        $chain = $approval->approver_config['approval_chain'] ?? [];
        $this->assertCount(1, $chain);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $chain[0]['approver_id']);
        $this->assertEquals(self::TEST_MEMBER_AGATHA_ID, $chain[0]['next_picked']);
    }

    public function testSerialPickNextAddsToExcludeList(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            null,
            self::TEST_MEMBER_AGATHA_ID
        );

        $approval = $this->approvalsTable->get($approvalId);
        $excludeIds = $approval->approver_config['exclude_member_ids'] ?? [];
        $this->assertContains(self::ADMIN_MEMBER_ID, $excludeIds);
    }

    public function testSerialPickNextRejectionStillRejects(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $result = $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_REJECT,
            'Not acceptable'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(WorkflowApproval::STATUS_REJECTED, $result->data['approvalStatus']);
    }

    public function testSerialPickNextWithNullNextApproverClearsCurrent(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'requiredCount' => 3,
            'approverConfig' => [
                'member_id' => self::ADMIN_MEMBER_ID,
                'serial_pick_next' => true,
                'current_approver_id' => self::ADMIN_MEMBER_ID,
                'approval_chain' => [],
            ],
        ]);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            null,
            null  // No next approver specified
        );

        $approval = $this->approvalsTable->get($approvalId);
        $config = $approval->approver_config;
        $this->assertArrayNotHasKey('current_approver_id', $config);
    }

    // =====================================================
    // recordResponse() — parallel (multi-approval threshold)
    // =====================================================

    public function testParallelApprovalStaysPendingBelowThreshold(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId, [
            'approverType' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
            'requiredCount' => 2,
        ]);

        $result = $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE
        );

        $this->assertTrue($result->isSuccess());
        // With member type + requiredCount=2, after 1 approval it hits threshold since approved_count(1) < required(2)
        // Actually with MEMBER approver type, only that member can respond. After first response, count=1 < 2
        // But the threshold check: approved_count >= required_count → 1 >= 2 → false
        // rejected_count > 0 → false
        // So status stays pending
        $approval = $this->approvalsTable->get($approvalId);
        // First response: approved_count becomes 1, but 1 < 2, so PENDING
        $this->assertEquals(WorkflowApproval::STATUS_PENDING, $approval->status);
    }

    // =====================================================
    // recordResponse() — with comment
    // =====================================================

    public function testRecordResponseSavesComment(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
            'This is my approval comment'
        );

        $response = $this->responsesTable->find()
            ->where(['workflow_approval_id' => $approvalId])
            ->first();
        $this->assertNotNull($response);
        $this->assertEquals('This is my approval comment', $response->comment);
    }

    public function testRecordResponseSavesDecision(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );

        $response = $this->responsesTable->find()
            ->where(['workflow_approval_id' => $approvalId])
            ->first();
        $this->assertEquals(WorkflowApprovalResponse::DECISION_APPROVE, $response->decision);
    }

    public function testRecordResponseSetsRespondedAt(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );

        $response = $this->responsesTable->find()
            ->where(['workflow_approval_id' => $approvalId])
            ->first();
        $this->assertNotNull($response->responded_at);
    }

    // =====================================================
    // cancelApprovalsForInstance() — edge cases
    // =====================================================

    public function testCancelApprovalsSkipsAlreadyResolved(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $logId);

        // Resolve the approval first
        $this->manager->recordResponse(
            $approvalId,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE
        );

        // Create another pending one
        $pendingId = $this->createApproval($instanceId, $logId);

        // Cancel should only affect the pending one
        $result = $this->manager->cancelApprovalsForInstance($instanceId);
        $this->assertTrue($result->isSuccess());

        $resolved = $this->approvalsTable->get($approvalId);
        $this->assertEquals(WorkflowApproval::STATUS_APPROVED, $resolved->status);
    }

    public function testCancelApprovalsForInstanceWithNoApprovals(): void
    {
        [, $instanceId,] = $this->createWorkflowContext();

        $result = $this->manager->cancelApprovalsForInstance($instanceId);
        $this->assertTrue($result->isSuccess());
    }

    // =====================================================
    // Multiple approvals on same instance
    // =====================================================

    public function testMultipleApprovalsOnSameInstance(): void
    {
        [, $instanceId, $logId] = $this->createWorkflowContext();
        $id1 = $this->createApproval($instanceId, $logId);
        $id2 = $this->createApproval($instanceId, $logId);

        $this->assertNotEquals($id1, $id2);

        $approvals = $this->manager->getApprovalsForInstance($instanceId);
        $this->assertGreaterThanOrEqual(2, count($approvals));
    }
}
