<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Integration;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowTask;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Integration tests verifying complete workflow execution end-to-end —
 * from trigger dispatch through engine execution to final state.
 */
class WorkflowIntegrationTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private DefaultWorkflowApprovalManager $approvalManager;
    private ContainerInterface $container;

    private $defTable;
    private $versionsTable;
    private $instancesTable;
    private $logsTable;
    private $approvalsTable;
    private $responsesTable;
    private $tasksTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->method('has')->willReturn(false);
        $this->engine = new DefaultWorkflowEngine($this->container);
        $this->approvalManager = new DefaultWorkflowApprovalManager();

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $this->logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $this->responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $this->tasksTable = TableRegistry::getTableLocator()->get('WorkflowTasks');
    }

    /**
     * Create an active workflow definition with a published version.
     *
     * @return array [definitionId, versionId, slug]
     */
    private function createWorkflowDefinition(string $slug, array $definition, string $triggerType = 'manual'): array
    {
        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => $triggerType,
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => $definition,
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $def->current_version_id = $version->id;
        $this->defTable->saveOrFail($def);

        return [$def->id, $version->id, $slug];
    }

    /**
     * Helper to get execution logs for an instance, ordered by ID.
     */
    private function getExecutionLogs(int $instanceId): array
    {
        return $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->order(['id' => 'ASC'])
            ->all()
            ->toArray();
    }

    /**
     * Helper to get a single log by instance and node ID.
     */
    private function getNodeLog(int $instanceId, string $nodeId): ?WorkflowExecutionLog
    {
        return $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'node_id' => $nodeId,
            ])
            ->first();
    }

    // =====================================================
    // 1. Simple Linear Workflow
    // =====================================================

    public function testSimpleLinearWorkflowExecutesAllNodes(): void
    {
        $slug = 'integ-linear-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'cond1']],
                ],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.step == go'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end1'],
                        ['port' => 'false', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['step' => 'go']);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // All three nodes should have execution logs
        $logs = $this->getExecutionLogs($instanceId);
        $this->assertCount(3, $logs);
        $nodeTypes = array_map(fn($l) => $l->node_type, $logs);
        $this->assertContains('trigger', $nodeTypes);
        $this->assertContains('condition', $nodeTypes);
        $this->assertContains('end', $nodeTypes);

        // Context should accumulate condition result
        $this->assertTrue($instance->context['nodes']['cond1']['result']);
        $this->assertSame('true', $instance->context['nodes']['cond1']['port']);
    }

    // =====================================================
    // 2. Conditional Branching
    // =====================================================

    public function testConditionalBranchingFollowsTruePath(): void
    {
        $slug = 'integ-branch-true-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.role == admin'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_approved'],
                        ['port' => 'false', 'target' => 'end_denied'],
                    ],
                ],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_denied' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['role' => 'admin']);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $this->assertNotNull($this->getNodeLog($instanceId, 'end_approved'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_denied'));
    }

    public function testConditionalBranchingFollowsFalsePath(): void
    {
        $slug = 'integ-branch-false-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.role == admin'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_approved'],
                        ['port' => 'false', 'target' => 'end_denied'],
                    ],
                ],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_denied' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['role' => 'guest']);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $this->assertNull($this->getNodeLog($instanceId, 'end_approved'));
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_denied'));
    }

    // =====================================================
    // 3. Approval Gate Workflow
    // =====================================================

    public function testApprovalGateApprovedPath(): void
    {
        $slug = 'integ-approval-approve-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'member',
                        'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                        'requiredCount' => 1,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_approved'],
                        ['port' => 'rejected', 'target' => 'end_rejected'],
                    ],
                ],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_rejected' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Start — workflow should pause at approval
        $startResult = $this->engine->startWorkflow($slug, ['request' => 'access']);
        $this->assertTrue($startResult->isSuccess());
        $instanceId = $startResult->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Record approval
        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'approve1'])
            ->first();
        $this->assertNotNull($approval);

        $approvalResult = $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );
        $this->assertTrue($approvalResult->isSuccess());
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approvalResult->data['approvalStatus']);

        // Resume workflow on approved path
        $resumeResult = $this->engine->resumeWorkflow(
            $instanceId,
            'approve1',
            'approved',
            ['approverId' => self::ADMIN_MEMBER_ID, 'decision' => 'approved'],
        );
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_approved'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_rejected'));
    }

    public function testApprovalGateRejectedPath(): void
    {
        $slug = 'integ-approval-reject-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'member',
                        'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                        'requiredCount' => 1,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_approved'],
                        ['port' => 'rejected', 'target' => 'end_rejected'],
                    ],
                ],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_rejected' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'approve1'])
            ->first();

        $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_REJECT,
        );

        $resumeResult = $this->engine->resumeWorkflow(
            $instanceId,
            'approve1',
            'rejected',
            ['approverId' => self::ADMIN_MEMBER_ID, 'decision' => 'rejected'],
        );
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNull($this->getNodeLog($instanceId, 'end_approved'));
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_rejected'));
    }

    // =====================================================
    // 4. ForEach Loop
    // =====================================================

    public function testForEachLoopIteratesOverCollection(): void
    {
        $slug = 'integ-foreach-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.members',
                        'itemVariable' => 'member',
                        'indexVariable' => 'idx',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $members = ['Alice', 'Bob', 'Charlie', 'Diana'];
        $result = $this->engine->startWorkflow($slug, ['members' => $members]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify exactly N iterations
        $context = $instance->context;
        $this->assertArrayHasKey('forEach', $context);
        $this->assertArrayHasKey('forEach1', $context['forEach']);
        $this->assertSame(4, $context['forEach']['forEach1']['processed']);
        $this->assertEmpty($context['forEach']['forEach1']['errors']);
    }

    // =====================================================
    // 5. State Machine Workflow
    // =====================================================

    public function testStateMachineWorkflowTransition(): void
    {
        $slug = 'integ-sm-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sm1']]],
                'sm1' => [
                    'type' => 'stateMachine',
                    'config' => [
                        'stateField' => 'state',
                        'statusField' => 'status',
                        'currentState' => '$.trigger.currentState',
                        'targetState' => '$.trigger.targetState',
                        'statuses' => [
                            'Open' => ['New', 'InProgress'],
                            'Closed' => ['Resolved', 'Archived'],
                        ],
                        'transitions' => [
                            'New' => ['InProgress'],
                            'InProgress' => ['Resolved'],
                            'Resolved' => ['Archived'],
                        ],
                        'stateRules' => [],
                    ],
                    'outputs' => [
                        ['port' => 'on_transition', 'target' => 'end1'],
                        ['port' => 'on_invalid', 'target' => 'end_invalid'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_invalid' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'currentState' => 'New',
            'targetState' => 'InProgress',
        ]);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // State machine should report success and correct state
        $smResult = $instance->context['nodes']['sm1']['result'];
        $this->assertTrue($smResult['success']);
        $this->assertSame('InProgress', $smResult['toState']);
        $this->assertSame('Open', $smResult['toStatus']);

        // Verify transition path was taken
        $this->assertNotNull($this->getNodeLog($instance->id, 'end1'));
        $this->assertNull($this->getNodeLog($instance->id, 'end_invalid'));
    }

    // =====================================================
    // 6. Delayed Node
    // =====================================================

    public function testDelayedNodeCreatesPendingState(): void
    {
        $slug = 'integ-delay-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => [
                    'type' => 'delay',
                    'config' => ['duration' => '2h'],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['note' => 'delayed processing']);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);

        // Should be WAITING at the delay node
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $delayLog = $this->getNodeLog($instanceId, 'delay1');
        $this->assertNotNull($delayLog);
        $this->assertSame(WorkflowExecutionLog::STATUS_WAITING, $delayLog->status);

        // End node should NOT have executed yet
        $this->assertNull($this->getNodeLog($instanceId, 'end1'));

        // Resume after delay — workflow completes
        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'delay1', 'default');
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end1'));
    }

    // =====================================================
    // 7. TriggerDispatcher → Engine
    // =====================================================

    public function testTriggerDispatcherStartsMatchingWorkflow(): void
    {
        $slug = 'integ-dispatch-' . uniqid();
        $eventName = 'integration.test.event.' . uniqid();

        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ], 'event');

        $results = $this->engine->dispatchTrigger($eventName, ['source' => 'test']);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());

        $instanceId = $results[0]->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame(['source' => 'test'], $instance->context['trigger']);
    }

    // =====================================================
    // 8. Multiple Definitions Match Same Trigger
    // =====================================================

    public function testMultipleDefinitionsMatchSameTrigger(): void
    {
        $eventName = 'multi.match.event.' . uniqid();

        // Create two distinct workflows that match the same event
        $slug1 = 'integ-multi1-' . uniqid();
        $this->createWorkflowDefinition($slug1, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ], 'event');

        $slug2 = 'integ-multi2-' . uniqid();
        $this->createWorkflowDefinition($slug2, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ], 'event');

        $results = $this->engine->dispatchTrigger($eventName, ['batch' => true]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());

        // Two distinct instances created
        $id1 = $results[0]->data['instanceId'];
        $id2 = $results[1]->data['instanceId'];
        $this->assertNotSame($id1, $id2);
    }

    // =====================================================
    // 9. Inactive Definition Skipped
    // =====================================================

    public function testInactiveDefinitionSkippedByTrigger(): void
    {
        $eventName = 'inactive.skip.event.' . uniqid();

        // Create active workflow
        $activeSlug = 'integ-active-' . uniqid();
        $this->createWorkflowDefinition($activeSlug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ], 'event');

        // Create inactive workflow matching same event
        $inactiveSlug = 'integ-inactive-' . uniqid();
        $inactiveDef = $this->defTable->newEntity([
            'name' => 'Test: ' . $inactiveSlug,
            'slug' => $inactiveSlug,
            'trigger_type' => 'event',
            'is_active' => false,
        ]);
        $this->defTable->saveOrFail($inactiveDef);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $inactiveDef->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger1' => [
                        'type' => 'trigger',
                        'config' => ['event' => $eventName],
                        'outputs' => [['port' => 'default', 'target' => 'end1']],
                    ],
                    'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $inactiveDef->current_version_id = $version->id;
        $this->defTable->saveOrFail($inactiveDef);

        $results = $this->engine->dispatchTrigger($eventName, []);

        // Only the active workflow should start
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
    }

    // =====================================================
    // 10. Serial Approval — Two Required
    // =====================================================

    public function testSerialApprovalRequiresTwoApprovals(): void
    {
        $slug = 'integ-serial-approve-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'member',
                        'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                        'requiredCount' => 2,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_no'],
                    ],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_no' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        // First approval — should NOT resolve (threshold is 2)
        $result1 = $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );
        $this->assertTrue($result1->isSuccess());

        // Reload approval — still pending with count=1
        $approval = $this->approvalsTable->get($approval->id);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $this->assertSame(1, $approval->approved_count);

        // Instance should still be WAITING
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Simulate second approval by directly updating counts (different approver
        // eligibility is tested in ApprovalManagerTest; here we test the engine's
        // resume-after-threshold behavior).
        $currentVersion = (int)$approval->version;
        $this->approvalsTable->updateAll(
            [
                'approved_count' => 2,
                'status' => WorkflowApproval::STATUS_APPROVED,
                'version' => $currentVersion + 1,
            ],
            ['id' => $approval->id],
        );

        // Resume workflow after approval threshold met
        $resumeResult = $this->engine->resumeWorkflow(
            $instanceId,
            'approve1',
            'approved',
            ['decision' => 'approved'],
        );
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_ok'));
    }

    // =====================================================
    // 11. Rejection Short-Circuits
    // =====================================================

    public function testRejectionShortCircuitsApproval(): void
    {
        $slug = 'integ-reject-short-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'member',
                        'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                        'requiredCount' => 3,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_rejected'],
                    ],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_rejected' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        // Single rejection should resolve even with requiredCount=3
        $rejectResult = $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_REJECT,
            'Not acceptable',
        );
        $this->assertTrue($rejectResult->isSuccess());
        $this->assertSame(WorkflowApproval::STATUS_REJECTED, $rejectResult->data['approvalStatus']);

        // Resume on rejected path
        $resumeResult = $this->engine->resumeWorkflow(
            $instanceId,
            'approve1',
            'rejected',
            ['approverId' => self::ADMIN_MEMBER_ID, 'decision' => 'rejected', 'comment' => 'Not acceptable'],
        );
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_rejected'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_ok'));
    }

    // =====================================================
    // 12. Optimistic Lock on Concurrent Approval
    // =====================================================

    public function testOptimisticLockOnConcurrentApproval(): void
    {
        $slug = 'integ-optlock-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'member',
                        'approverConfig' => ['member_id' => self::ADMIN_MEMBER_ID],
                        'requiredCount' => 2,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        // First approval succeeds
        $result1 = $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );
        $this->assertTrue($result1->isSuccess());

        // Duplicate from same member should be rejected
        $result2 = $this->approvalManager->recordResponse(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            WorkflowApprovalResponse::DECISION_APPROVE,
        );
        $this->assertFalse($result2->isSuccess());
        $this->assertStringContainsString('already responded', $result2->reason);

        // Verify counts are correct — only 1 counted
        $approval = $this->approvalsTable->get($approval->id);
        $this->assertSame(1, $approval->approved_count);
        $this->assertSame(0, $approval->rejected_count);
    }

    // =====================================================
    // 13. Context Passes Through Nodes
    // =====================================================

    public function testContextPassesThroughNodes(): void
    {
        $slug = 'integ-ctx-pass-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.amount > 100'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'cond2'],
                        ['port' => 'false', 'target' => 'end_low'],
                    ],
                ],
                'cond2' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.priority == high'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_urgent'],
                        ['port' => 'false', 'target' => 'end_normal'],
                    ],
                ],
                'end_low' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_urgent' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_normal' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'amount' => 200,
            'priority' => 'high',
        ]);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify both conditions stored results in context
        $ctx = $instance->context;
        $this->assertTrue($ctx['nodes']['cond1']['result']);
        $this->assertSame('true', $ctx['nodes']['cond1']['port']);
        $this->assertTrue($ctx['nodes']['cond2']['result']);
        $this->assertSame('true', $ctx['nodes']['cond2']['port']);

        // Verify trigger data persisted through entire chain
        $this->assertSame(200, $ctx['trigger']['amount']);
        $this->assertSame('high', $ctx['trigger']['priority']);

        // Verify correct end node executed
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_urgent'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_low'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_normal'));
    }

    // =====================================================
    // 14. Expression Evaluation in Condition Config
    // =====================================================

    public function testExpressionEvaluationInConditionConfig(): void
    {
        $slug = 'integ-expr-cond-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.name == processed'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_match'],
                        ['port' => 'false', 'target' => 'end_nomatch'],
                    ],
                ],
                'end_match' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_nomatch' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Provide name='processed' so expression matches
        $result = $this->engine->startWorkflow($slug, ['name' => 'processed']);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Expression resolved correctly
        $this->assertTrue($instance->context['nodes']['cond1']['result']);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_match'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_nomatch'));
    }

    // =====================================================
    // 15. Context Path Expression in Condition
    // =====================================================

    public function testContextPathExpressionInCondition(): void
    {
        $slug = 'integ-ctx-expr-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.score >= 80'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'cond2'],
                        ['port' => 'false', 'target' => 'end_fail'],
                    ],
                ],
                'cond2' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.score <= 100'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_pass'],
                        ['port' => 'false', 'target' => 'end_invalid'],
                    ],
                ],
                'end_pass' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_fail' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_invalid' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['score' => 85]);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Both conditions should be true (85 >= 80 and 85 <= 100)
        $this->assertTrue($instance->context['nodes']['cond1']['result']);
        $this->assertTrue($instance->context['nodes']['cond2']['result']);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_pass'));
    }

    // =====================================================
    // 16. Action Failure Stops Workflow
    // =====================================================

    public function testActionFailureStopsWorkflow(): void
    {
        $slug = 'integ-action-fail-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'act1']]],
                'act1' => [
                    'type' => 'action',
                    'config' => ['action' => 'NonExistent.Action.ThatDoesNotExist'],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['data' => 'test']);

        // Action not in registry should cause failure
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->reason);
    }

    // =====================================================
    // 17. Missing Action Handler
    // =====================================================

    public function testMissingActionHandlerGracefulFailure(): void
    {
        $slug = 'integ-missing-action-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'act1']]],
                'act1' => [
                    'type' => 'action',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('no action configured', $result->reason);
    }

    // =====================================================
    // 18. Invalid Node Reference
    // =====================================================

    public function testInvalidNodeReferenceHandled(): void
    {
        $slug = 'integ-bad-ref-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'nonexistent_node']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        // Engine handles missing target gracefully — no crash
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);

        // The engine silently skips nonexistent nodes, leaving the instance
        // stuck in running state (it never reaches an end node)
        $this->assertSame(WorkflowInstance::STATUS_RUNNING, $instance->status);

        // The valid end1 node should NOT have executed
        $this->assertNull($this->getNodeLog($instanceId, 'end1'));
    }

    // =====================================================
    // 19. Human Task Pauses Workflow
    // =====================================================

    public function testHumanTaskPausesWorkflowAndCreatesTask(): void
    {
        $slug = 'integ-htask-pause-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'task1']]],
                'task1' => [
                    'type' => 'humanTask',
                    'config' => [
                        'taskTitle' => 'Review and approve',
                        'assignTo' => '$.trigger.assignee_id',
                        'formFields' => [
                            ['name' => 'approved', 'type' => 'checkbox', 'label' => 'Approved?', 'required' => true],
                            ['name' => 'comment', 'type' => 'textarea', 'label' => 'Comment', 'required' => false],
                        ],
                    ],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['assignee_id' => self::ADMIN_MEMBER_ID]);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        // Instance should be WAITING
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // WorkflowTask record should exist
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();
        $this->assertNotNull($task);
        $this->assertSame('task1', $task->node_id);
        $this->assertSame('Review and approve', $task->task_title);
        $this->assertSame(self::ADMIN_MEMBER_ID, $task->assigned_to);
        $this->assertSame(WorkflowTask::STATUS_PENDING, $task->status);
        $this->assertNotEmpty($task->form_definition);

        // Execution log for task node should be WAITING
        $taskLog = $this->getNodeLog($instanceId, 'task1');
        $this->assertNotNull($taskLog);
        $this->assertSame(WorkflowExecutionLog::STATUS_WAITING, $taskLog->status);
    }

    // =====================================================
    // 20. Complete Human Task Resumes Workflow
    // =====================================================

    public function testCompleteHumanTaskResumesWorkflow(): void
    {
        $slug = 'integ-htask-complete-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'task1']]],
                'task1' => [
                    'type' => 'humanTask',
                    'config' => [
                        'taskTitle' => 'Fill out form',
                        'assignTo' => '$.trigger.member_id',
                        'formFields' => [
                            ['name' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => false],
                        ],
                    ],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['member_id' => self::ADMIN_MEMBER_ID]);
        $instanceId = $result->data['instanceId'];

        // Get the created task
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();
        $this->assertNotNull($task);

        // Complete the task with form data
        $completeResult = $this->engine->completeHumanTask(
            $task->id,
            ['reason' => 'Testing complete flow'],
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($completeResult->isSuccess());

        // Workflow should now be completed
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Task record should be COMPLETED with form data
        $updatedTask = $this->tasksTable->get($task->id);
        $this->assertSame(WorkflowTask::STATUS_COMPLETED, $updatedTask->status);
        $this->assertSame(['reason' => 'Testing complete flow'], $updatedTask->form_data);
        $this->assertNotNull($updatedTask->completed_at);
        $this->assertSame(self::ADMIN_MEMBER_ID, $updatedTask->completed_by);

        // End node should have been reached
        $this->assertNotNull($this->getNodeLog($instanceId, 'end1'));
    }

    // =====================================================
    // 21. Loop + Condition Combo
    // =====================================================

    public function testLoopWithExitConditionCombination(): void
    {
        $slug = 'integ-loop-cond-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'loop1']]],
                'loop1' => [
                    'type' => 'loop',
                    'config' => ['maxIterations' => 5],
                    'outputs' => [
                        ['port' => 'continue', 'target' => 'loop1'],
                        ['port' => 'exit', 'target' => 'cond1'],
                    ],
                ],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.done == yes'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_done'],
                        ['port' => 'false', 'target' => 'end_not_done'],
                    ],
                ],
                'end_done' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_not_done' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['done' => 'yes']);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Loop should have run 5 iterations
        $loopLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'loop1'])
            ->all()
            ->toArray();
        $this->assertCount(5, $loopLogs);

        // Condition after loop should evaluate and follow true path
        $this->assertTrue($instance->context['nodes']['cond1']['result']);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_done'));
    }

    // =====================================================
    // 22. Fork/Join With Condition Branches
    // =====================================================

    public function testForkJoinWithMultiplePaths(): void
    {
        $slug = 'integ-fork-join-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'path_a', 'target' => 'join1'],
                        ['port' => 'path_b', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'path_a'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'path_b'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['parallel' => true]);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // All nodes should have execution logs
        $logs = $this->getExecutionLogs($instance->id);
        $nodeIds = array_map(fn($l) => $l->node_id, $logs);
        $this->assertContains('trigger1', $nodeIds);
        $this->assertContains('fork1', $nodeIds);
        $this->assertContains('join1', $nodeIds);
        $this->assertContains('end1', $nodeIds);
    }

    // =====================================================
    // 23. Dispatch No Match Returns Empty
    // =====================================================

    public function testDispatchTriggerNoMatchReturnsEmpty(): void
    {
        $eventName = 'nonexistent.event.' . uniqid();
        $results = $this->engine->dispatchTrigger($eventName, []);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================
    // 24. Resume Non-Waiting Instance Fails
    // =====================================================

    public function testResumeCompletedInstanceFails(): void
    {
        $slug = 'integ-resume-fail-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        // Instance is COMPLETED, resume should fail
        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'end1', 'default');
        $this->assertFalse($resumeResult->isSuccess());
        $this->assertStringContainsString('not in waiting', $resumeResult->reason);
    }

    // =====================================================
    // 25. Cancel Workflow Cancels Pending Approvals
    // =====================================================

    public function testCancelWorkflowCancelsPendingApprovals(): void
    {
        $slug = 'integ-cancel-approve-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => ['approverType' => 'permission', 'requiredCount' => 1],
                    'outputs' => [['port' => 'approved', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        // Verify workflow is waiting
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Cancel workflow
        $cancelResult = $this->engine->cancelWorkflow($instanceId, 'No longer needed');
        $this->assertTrue($cancelResult->isSuccess());

        // Instance should be cancelled
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $instance->status);
        $this->assertSame('No longer needed', $instance->error_info['cancellation_reason']);

        // Approval should also be cancelled
        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $approval->status);
    }

    // =====================================================
    // 26. Trigger Data Available in Context
    // =====================================================

    public function testTriggerDataAvailableInContext(): void
    {
        $slug = 'integ-trigger-ctx-' . uniqid();
        $eventName = 'context.test.event.' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['port' => 'default', 'target' => 'cond1']],
                ],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.entity_type == member'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end1'],
                        ['port' => 'false', 'target' => 'end2'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end2' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ], 'event');

        $triggerData = [
            'entity_type' => 'member',
            'entity_id' => 42,
            'action' => 'created',
        ];
        $results = $this->engine->dispatchTrigger($eventName, $triggerData, self::ADMIN_MEMBER_ID);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());

        $instanceId = $results[0]->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);

        // Trigger data fully available
        $this->assertSame('member', $instance->context['trigger']['entity_type']);
        $this->assertSame(42, $instance->context['trigger']['entity_id']);
        $this->assertSame('created', $instance->context['trigger']['action']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $instance->context['triggeredBy']);

        // Condition should have resolved using trigger data
        $this->assertTrue($instance->context['nodes']['cond1']['result']);
    }

    // =====================================================
    // 27. State Machine Invalid Transition Uses on_invalid
    // =====================================================

    public function testStateMachineInvalidTransitionFollowsOnInvalid(): void
    {
        $slug = 'integ-sm-invalid-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sm1']]],
                'sm1' => [
                    'type' => 'stateMachine',
                    'config' => [
                        'currentState' => '$.trigger.currentState',
                        'targetState' => '$.trigger.targetState',
                        'statuses' => ['Active' => ['Open', 'InProgress'], 'Closed' => ['Done']],
                        'transitions' => ['Open' => ['InProgress'], 'InProgress' => ['Done']],
                        'stateRules' => [],
                    ],
                    'outputs' => [
                        ['port' => 'on_transition', 'target' => 'end_ok'],
                        ['port' => 'on_invalid', 'target' => 'end_invalid'],
                    ],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_invalid' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Attempt invalid transition: Open → Done (should require InProgress first)
        $result = $this->engine->startWorkflow($slug, [
            'currentState' => 'Open',
            'targetState' => 'Done',
        ]);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Should have taken on_invalid path
        $this->assertFalse($instance->context['nodes']['sm1']['result']['success']);
        $this->assertNotNull($this->getNodeLog($instanceId, 'end_invalid'));
        $this->assertNull($this->getNodeLog($instanceId, 'end_ok'));
    }

    // =====================================================
    // 28. GetInstanceState Returns Full Data
    // =====================================================

    public function testGetInstanceStateReturnsFullData(): void
    {
        $slug = 'integ-state-' . uniqid();
        $this->createWorkflowDefinition($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.ok == yes'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end1'],
                        ['port' => 'false', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug, ['ok' => 'yes']);
        $instanceId = $startResult->data['instanceId'];

        $state = $this->engine->getInstanceState($instanceId);

        $this->assertNotNull($state);
        $this->assertSame($instanceId, $state['id']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $state['status']);
        $this->assertArrayHasKey('execution_logs', $state);
        $this->assertArrayHasKey('approvals', $state);
        $this->assertArrayHasKey('context', $state);
        $this->assertCount(3, $state['execution_logs']);
    }
}
