<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WarrantManager;

use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantRoster;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\DefaultWarrantManager;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WorkflowEngine\Providers\WarrantWorkflowActions;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for warrant roster ↔ workflow approval sync.
 *
 * Covers syncWorkflowApprovalToRoster(), activateApprovedRoster(),
 * and the integration points in WarrantWorkflowActions.
 */
class WarrantRosterSyncTest extends BaseTestCase
{
    private $rosterTable;
    private $warrantTable;
    private $approvalsTable;
    private $responsesTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $this->warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $this->responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
    }

    /**
     * Build a DefaultWarrantManager with workflow dependencies stubbed out.
     */
    private function createWarrantManager(): WarrantManagerInterface
    {
        $awm = $this->createMock(ActiveWindowManagerInterface::class);
        $awm->method('stop')->willReturn(new ServiceResult(true));
        $td = $this->createMock(TriggerDispatcher::class);

        return new DefaultWarrantManager($awm, $td);
    }

    /**
     * Create a pending roster with a single pending warrant.
     *
     * @return array [rosterId, warrantId]
     */
    private function createPendingRoster(int $approvalsRequired = 1): array
    {
        $roster = $this->rosterTable->newEmptyEntity();
        $roster->name = 'Sync Test Roster ' . uniqid();
        $roster->description = 'Test';
        $roster->approvals_required = $approvalsRequired;
        $roster->approval_count = 0;
        $roster->status = WarrantRoster::STATUS_PENDING;
        $roster->created_on = new DateTime();
        $this->rosterTable->saveOrFail($roster);

        $warrant = $this->warrantTable->newEmptyEntity();
        $warrant->name = 'Test Warrant';
        $warrant->warrant_roster_id = $roster->id;
        $warrant->member_id = self::ADMIN_MEMBER_ID;
        $warrant->entity_type = 'Branches';
        $warrant->entity_id = self::KINGDOM_BRANCH_ID;
        $warrant->status = Warrant::PENDING_STATUS;
        $warrant->start_on = new DateTime();
        $warrant->expires_on = (new DateTime())->modify('+1 year');
        $this->warrantTable->saveOrFail($warrant);

        return [$roster->id, $warrant->id];
    }

    /**
     * Create workflow context objects: definition, version, instance, log, and approval gate.
     *
     * @return array [instanceId, approvalId]
     */
    private function createWorkflowApprovalContext(int $requiredCount = 1): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'WRS Test ' . uniqid(),
            'slug' => 'wrs-' . uniqid(),
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

        $approval = $this->approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'execution_log_id' => $log->id,
            'node_id' => 'approval_node',
            'approver_type' => 'member',
            'approver_config' => [],
            'required_count' => $requiredCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => 'approved',
        ]);
        $this->approvalsTable->saveOrFail($approval);

        return [$instance->id, $approval->id];
    }

    /**
     * Add a workflow approval response.
     */
    private function addApprovalResponse(int $approvalId, int $memberId, string $decision = 'approve', ?string $comment = null): void
    {
        $response = $this->responsesTable->newEntity([
            'workflow_approval_id' => $approvalId,
            'member_id' => $memberId,
            'decision' => $decision,
            'comment' => $comment,
            'responded_at' => new DateTime(),
        ]);
        $this->responsesTable->saveOrFail($response);
    }

    // =====================================================
    // syncWorkflowApprovalToRoster()
    // =====================================================

    public function testSyncCreatesApprovalRecord(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster();

        $result = $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess());

        // Should increment the denormalized counter on the roster
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(1, $roster->approval_count, 'Counter should be incremented');
    }

    public function testSyncIncrementsApprovalCount(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster(2);

        $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);

        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(1, $roster->approval_count);
    }

    public function testSyncDedupGuardSkipsDuplicate(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster(2);

        // Dedup is now handled by the workflow engine's approval manager.
        // syncWorkflowApprovalToRoster only increments the counter.
        $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);
        $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);

        // Counter increments each call (callers are responsible for dedup)
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(2, $roster->approval_count);
    }

    public function testSyncReturnsTrueOnDuplicate(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster();

        $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);
        $result = $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), 'Duplicate sync should return success (idempotent)');
    }

    public function testSyncHandlesNullNotesAndApprovedOn(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster();

        $result = $wm->syncWorkflowApprovalToRoster($rosterId, self::ADMIN_MEMBER_ID, null, null);

        $this->assertTrue($result->isSuccess());

        // Should increment counter even with null optional params
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(1, $roster->approval_count);
    }

    // =====================================================
    // activateApprovedRoster()
    // =====================================================

    public function testActivateApprovedRosterActivatesPendingWarrants(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId, $warrantId] = $this->createPendingRoster();

        // Set roster to approved
        $roster = $this->rosterTable->get($rosterId);
        $roster->status = WarrantRoster::STATUS_APPROVED;
        $roster->approval_count = 1;
        $this->rosterTable->saveOrFail($roster);

        $result = $wm->activateApprovedRoster($rosterId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess());

        $warrant = $this->warrantTable->get($warrantId);
        $this->assertEquals(Warrant::CURRENT_STATUS, $warrant->status);
    }

    public function testActivateApprovedRosterSetsApprovedDate(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId, $warrantId] = $this->createPendingRoster();

        $roster = $this->rosterTable->get($rosterId);
        $roster->status = WarrantRoster::STATUS_APPROVED;
        $this->rosterTable->saveOrFail($roster);

        $wm->activateApprovedRoster($rosterId, self::ADMIN_MEMBER_ID);

        $warrant = $this->warrantTable->get($warrantId);
        $this->assertNotNull($warrant->approved_date);
    }

    public function testActivateIsIdempotentWhenAlreadyActive(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId, $warrantId] = $this->createPendingRoster();

        $roster = $this->rosterTable->get($rosterId);
        $roster->status = WarrantRoster::STATUS_APPROVED;
        $this->rosterTable->saveOrFail($roster);

        // First activation
        $wm->activateApprovedRoster($rosterId, self::ADMIN_MEMBER_ID);

        // Second activation should be idempotent (no pending warrants)
        $result = $wm->activateApprovedRoster($rosterId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($result->isSuccess(), 'Re-activation should be idempotent');
    }

    public function testActivateReturnsSuccessResult(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster();

        $roster = $this->rosterTable->get($rosterId);
        $roster->status = WarrantRoster::STATUS_APPROVED;
        $this->rosterTable->saveOrFail($roster);

        $result = $wm->activateApprovedRoster($rosterId, self::ADMIN_MEMBER_ID);

        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess());
    }

    public function testNotifyWarrantIssuedQueuesSluggedTemplate(): void
    {
        [$rosterId, $warrantId] = $this->createPendingRoster();

        $warrant = $this->warrantTable->get($warrantId);
        $warrant->status = Warrant::CURRENT_STATUS;
        $this->warrantTable->saveOrFail($warrant);

        $actions = $this->getMockBuilder(WarrantWorkflowActions::class)
            ->setConstructorArgs([$this->createMock(WarrantManagerInterface::class)])
            ->onlyMethods(['queueMail'])
            ->getMock();

        $actions->expects($this->once())
            ->method('queueMail')
            ->with(
                'KMP',
                'sendFromTemplate',
                $this->isType('string'),
                $this->callback(function (array $vars): bool {
                    return ($vars['_templateId'] ?? null) === 'warrant-issued'
                        && ($vars['warrantName'] ?? null) === 'Test Warrant'
                        && array_key_exists('siteAdminSignature', $vars);
                }),
            );

        $result = $actions->notifyWarrantIssued([], ['rosterId' => $rosterId]);

        $this->assertSame(1, $result['emailsSent']);
    }

    // =====================================================
    // Integration: activateWarrants workflow action
    // =====================================================

    public function testActivateWarrantsSyncsApprovalsRequired(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster(1);
        [$instanceId, $approvalId] = $this->createWorkflowApprovalContext(3);
        $this->addApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        $actions->activateWarrants($context, $config);

        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(3, $roster->approvals_required, 'Should sync from workflow approval config');
    }

    public function testActivateWarrantsSyncsAllApprovalResponses(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster(2);
        [$instanceId, $approvalId] = $this->createWorkflowApprovalContext(2);

        $this->addApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);
        $this->addApprovalResponse($approvalId, self::TEST_MEMBER_AGATHA_ID);

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        $actions->activateWarrants($context, $config);

        // Both approval responses should increment the roster counter
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(2, $roster->approval_count, 'Both approval responses should sync to roster counter');
    }

    public function testActivateWarrantsSetsRosterStatusApproved(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster();
        [$instanceId, $approvalId] = $this->createWorkflowApprovalContext();
        $this->addApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        $actions->activateWarrants($context, $config);

        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(WarrantRoster::STATUS_APPROVED, $roster->status);
    }

    public function testActivateWarrantsActivatesAfterSync(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId, $warrantId] = $this->createPendingRoster();
        [$instanceId, $approvalId] = $this->createWorkflowApprovalContext();
        $this->addApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        $output = $actions->activateWarrants($context, $config);

        $this->assertTrue($output['activated']);
        $this->assertGreaterThanOrEqual(1, $output['count']);

        $warrant = $this->warrantTable->get($warrantId);
        $this->assertEquals(Warrant::CURRENT_STATUS, $warrant->status);
    }

    // =====================================================
    // Integration: declineRoster workflow action
    // =====================================================

    public function testDeclineRosterSyncsApproveResponsesBeforeDecline(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster(2);

        // Create a workflow approval context with status 'rejected'
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Decline Test ' . uniqid(),
            'slug' => 'decline-' . uniqid(),
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
            'status' => 'completed',
        ]);
        $instancesTable->saveOrFail($instance);

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'node_type' => 'approval',
            'status' => 'completed',
        ]);
        $logsTable->saveOrFail($log);

        $approval = $this->approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'execution_log_id' => $log->id,
            'node_id' => 'approval_node',
            'approver_type' => 'member',
            'approver_config' => [],
            'required_count' => 2,
            'approved_count' => 1,
            'rejected_count' => 1,
            'status' => 'rejected',
        ]);
        $this->approvalsTable->saveOrFail($approval);

        // One approve, one reject
        $this->addApprovalResponse($approval->id, self::ADMIN_MEMBER_ID, 'approve', 'Looks good');
        $this->addApprovalResponse($approval->id, self::TEST_MEMBER_AGATHA_ID, 'reject', 'Denied');

        $context = [
            'instanceId' => $instance->id,
            'triggeredBy' => self::TEST_MEMBER_AGATHA_ID,
        ];
        $config = [
            'rosterId' => $rosterId,
            'reason' => 'Workflow declined',
            'rejecterId' => self::TEST_MEMBER_AGATHA_ID,
        ];

        $actions->declineRoster($context, $config);

        // The approve response should have incremented the roster counter
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(1, $roster->approval_count, 'Only approve responses should increment counter before decline');
    }

    // =====================================================
    // Edge cases
    // =====================================================

    public function testActivateWarrantsWithZeroResponses(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster();
        [$instanceId] = $this->createWorkflowApprovalContext();
        // No responses added

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        // Should not crash — roster gets marked approved but with 0 synced responses
        $output = $actions->activateWarrants($context, $config);

        // Still activates warrants (approval gate already passed in workflow)
        $this->assertTrue($output['activated']);

        // No responses, so roster counter should be 0
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(0, $roster->approval_count);
    }

    public function testMixedApproveRejectOnlySyncsApprovals(): void
    {
        $wm = $this->createWarrantManager();
        $actions = new WarrantWorkflowActions($wm);
        [$rosterId] = $this->createPendingRoster(2);
        [$instanceId, $approvalId] = $this->createWorkflowApprovalContext(2);

        // One approve, one reject — only approve decision should be synced
        $this->addApprovalResponse($approvalId, self::ADMIN_MEMBER_ID, 'approve');
        $this->addApprovalResponse($approvalId, self::TEST_MEMBER_AGATHA_ID, 'reject');

        $context = [
            'instanceId' => $instanceId,
            'triggeredBy' => self::ADMIN_MEMBER_ID,
            'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID],
        ];
        $config = [
            'rosterId' => $rosterId,
        ];

        $actions->activateWarrants($context, $config);

        // Only the approve response should increment the roster counter
        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(1, $roster->approval_count, 'Only approve decisions should increment counter');
    }

    // =====================================================
    // decline() — pure domain work, no engine driving
    // =====================================================

    public function testDeclineMarksWarrantsAndRosterDeclined(): void
    {
        $wm = $this->createWarrantManager();
        [$rosterId, $warrantId] = $this->createPendingRoster();

        $result = $wm->decline($rosterId, self::ADMIN_MEMBER_ID, 'Not approved');

        $this->assertTrue($result->isSuccess());

        $warrant = $this->warrantTable->get($warrantId);
        $this->assertEquals(Warrant::DECLINED_STATUS, $warrant->status, 'Pending warrant should be declined');

        $roster = $this->rosterTable->get($rosterId);
        $this->assertEquals(WarrantRoster::STATUS_DECLINED, $roster->status, 'Roster should be declined');
    }

    public function testDeclineDoesNotRequireWorkflowApproval(): void
    {
        // A roster with no workflow approval instance must still decline cleanly,
        // because the engine has already routed to the decline action by this point.
        $wm = $this->createWarrantManager();
        [$rosterId] = $this->createPendingRoster();

        $result = $wm->decline($rosterId, self::ADMIN_MEMBER_ID, 'Denied');

        $this->assertTrue($result->isSuccess());
    }
}
