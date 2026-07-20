<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Dummy action service that records invocations for assertion.
 */
class IntermediateActionTracker
{
    /** @var array<int, array{context: array, config: array}> */
    public static array $calls = [];

    public function execute(array $context, array $config): array
    {
        self::$calls[] = ['context' => $context, 'config' => $config];

        return ['tracked' => true, 'callIndex' => count(self::$calls)];
    }

    public static function reset(): void
    {
        self::$calls = [];
    }
}

/**
 * Tests for the on_each_approval output port on approval nodes.
 *
 * Verifies fireIntermediateApprovalActions() executes targets connected
 * to the on_each_approval port for non-final approvals while keeping
 * the instance in WAITING state.
 */
class IntermediateApprovalActionsTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private $defTable;
    private $versionsTable;
    private $instancesTable;
    private $logsTable;
    private $approvalsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $tracker = new IntermediateActionTracker();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id) {
            return $id === IntermediateActionTracker::class;
        });
        $container->method('get')->willReturnCallback(function (string $id) use ($tracker) {
            if ($id === IntermediateActionTracker::class) {
                return $tracker;
            }
            throw new \RuntimeException("Service '{$id}' not registered in test container.");
        });
        $this->engine = new DefaultWorkflowEngine($container);

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $this->logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

        IntermediateActionTracker::reset();

        // Always re-register: other test classes may call WorkflowActionRegistry::clear()
        WorkflowActionRegistry::register('TestIntermediate', [
            [
                'action' => 'TestIntermediate.Track',
                'label' => 'Track Intermediate Action',
                'description' => 'Test action that records calls for assertion',
                'inputSchema' => [],
                'outputSchema' => [],
                'serviceClass' => IntermediateActionTracker::class,
                'serviceMethod' => 'execute',
            ],
        ]);
    }

    /**
     * Skip if fireIntermediateApprovalActions is not yet implemented.
     */
    private function requireIntermediateMethod(): void
    {
        if (!method_exists($this->engine, 'fireIntermediateApprovalActions')) {
            $this->markTestSkipped(
                'DefaultWorkflowEngine::fireIntermediateApprovalActions() not yet implemented.'
            );
        }
    }

    /**
     * Create an active workflow definition with a published version.
     * Returns [definitionId, versionId, slug].
     */
    private function createWorkflow(string $slug, array $definition): array
    {
        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => 'manual',
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
     * Build a 3-approval serial pick-next workflow with on_each_approval port.
     * Returns [instanceId, approvalNodeId].
     */
    private function buildSerialApprovalWorkflow(): array
    {
        $slug = 'serial-intermediate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'approve1']],
                ],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'requiredCount' => 3,
                        'serialPickNext' => true,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_nope'],
                        ['port' => 'on_each_approval', 'target' => 'notify_action'],
                    ],
                ],
                'notify_action' => [
                    'type' => 'action',
                    'config' => ['action' => 'TestIntermediate.Track'],
                    'outputs' => [],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_nope' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess(), 'Workflow should start successfully');
        $instanceId = $result->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        return [$instanceId, 'approve1'];
    }

    /**
     * Build a parallel approval workflow with on_each_approval port.
     * Returns [instanceId, approvalNodeId].
     */
    private function buildParallelApprovalWorkflow(): array
    {
        $slug = 'parallel-intermediate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'approve1']],
                ],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'requiredCount' => 3,
                        'allowParallel' => true,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_nope'],
                        ['port' => 'on_each_approval', 'target' => 'notify_action'],
                    ],
                ],
                'notify_action' => [
                    'type' => 'action',
                    'config' => ['action' => 'TestIntermediate.Track'],
                    'outputs' => [],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_nope' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        return [$result->data['instanceId'], 'approve1'];
    }

    // =====================================================
    // Test 1: Serial pick-next intermediate approval fires
    //         on_each_approval targets, stays WAITING
    // =====================================================

    public function testSerialIntermediateApprovalFiresOnEachApprovalTargets(): void
    {
        $this->requireIntermediateMethod();
        [$instanceId, $nodeId] = $this->buildSerialApprovalWorkflow();

        // --- 1st intermediate approval ---
        $result1 = $this->engine->fireIntermediateApprovalActions($instanceId, $nodeId, [
            'approvedCount' => 1,
            'requiredCount' => 3,
            'approverId' => self::ADMIN_MEMBER_ID,
            'nextApproverId' => self::TEST_MEMBER_AGATHA_ID,
            'approvalChain' => [['approver_id' => self::ADMIN_MEMBER_ID]],
            'decision' => 'approve',
        ]);

        $this->assertTrue($result1->isSuccess(), 'First intermediate approval should succeed');
        $this->assertCount(1, IntermediateActionTracker::$calls,
            'on_each_approval target should fire once after 1st approval');

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status,
            'Instance must stay WAITING after 1st intermediate approval');

        // --- 2nd intermediate approval ---
        $result2 = $this->engine->fireIntermediateApprovalActions($instanceId, $nodeId, [
            'approvedCount' => 2,
            'requiredCount' => 3,
            'approverId' => self::TEST_MEMBER_AGATHA_ID,
            'nextApproverId' => self::TEST_MEMBER_BRYCE_ID,
            'approvalChain' => [
                ['approver_id' => self::ADMIN_MEMBER_ID],
                ['approver_id' => self::TEST_MEMBER_AGATHA_ID],
            ],
            'decision' => 'approve',
        ]);

        $this->assertTrue($result2->isSuccess(), 'Second intermediate approval should succeed');
        $this->assertCount(2, IntermediateActionTracker::$calls,
            'on_each_approval target should fire twice after 2nd approval');

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status,
            'Instance must stay WAITING after 2nd intermediate approval');

        // --- 3rd (final) approval via resumeWorkflow ---
        $finalResult = $this->engine->resumeWorkflow($instanceId, $nodeId, 'approved', [
            'approverId' => self::TEST_MEMBER_BRYCE_ID,
            'decision' => 'approved',
            'comment' => 'Final approval',
        ]);

        $this->assertTrue($finalResult->isSuccess(), 'Final approval resume should succeed');

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status,
            'Instance must complete after final approval');

        // on_each_approval must NOT fire for the final approval
        $this->assertCount(2, IntermediateActionTracker::$calls,
            'on_each_approval must NOT fire on the final approval — still 2');

        // Verify end_ok was executed via approved port
        $endOkLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_ok'])
            ->first();
        $this->assertNotNull($endOkLog, 'end_ok node should execute on final approval');
    }

    // =====================================================
    // Test 2: Parallel approval intermediate fires
    //         on_each_approval targets
    // =====================================================

    public function testParallelIntermediateApprovalFiresOnEachApprovalTargets(): void
    {
        $this->requireIntermediateMethod();
        [$instanceId, $nodeId] = $this->buildParallelApprovalWorkflow();

        // Approval 1 of 3 (intermediate)
        $result1 = $this->engine->fireIntermediateApprovalActions($instanceId, $nodeId, [
            'approvedCount' => 1,
            'requiredCount' => 3,
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'approve',
        ]);

        $this->assertTrue($result1->isSuccess());
        $this->assertCount(1, IntermediateActionTracker::$calls);
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Approval 2 of 3 (intermediate)
        $result2 = $this->engine->fireIntermediateApprovalActions($instanceId, $nodeId, [
            'approvedCount' => 2,
            'requiredCount' => 3,
            'approverId' => self::TEST_MEMBER_AGATHA_ID,
            'decision' => 'approve',
        ]);

        $this->assertTrue($result2->isSuccess());
        $this->assertCount(2, IntermediateActionTracker::$calls);
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Approval 3 of 3 (final) — resume via approved port
        $finalResult = $this->engine->resumeWorkflow($instanceId, $nodeId, 'approved', [
            'approverId' => self::TEST_MEMBER_BRYCE_ID,
            'decision' => 'approved',
        ]);

        $this->assertTrue($finalResult->isSuccess());
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertCount(2, IntermediateActionTracker::$calls,
            'on_each_approval must not fire for the final resolution');
    }

    // =====================================================
    // Test 3: Backward compatibility — no on_each_approval
    //         edges means no errors, no spurious fires
    // =====================================================

    public function testNoOnEachApprovalEdgesBackwardCompatible(): void
    {
        $this->requireIntermediateMethod();

        $slug = 'compat-no-port-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'approve1']],
                ],
                'approve1' => [
                    'type' => 'approval',
                    'config' => ['approverType' => 'permission', 'requiredCount' => 3],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_nope'],
                    ],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_nope' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        // Call fireIntermediateApprovalActions on a node with no on_each_approval edges
        $intermediateResult = $this->engine->fireIntermediateApprovalActions($instanceId, 'approve1', [
            'approvedCount' => 1,
            'requiredCount' => 3,
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'approve',
        ]);

        $this->assertTrue($intermediateResult->isSuccess(),
            'Must succeed even without on_each_approval edges');
        $this->assertCount(0, IntermediateActionTracker::$calls,
            'No targets should fire when on_each_approval has no edges');

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status,
            'Instance must remain WAITING');
    }

    // =====================================================
    // Test 4: Rejection does NOT fire on_each_approval
    // =====================================================

    public function testRejectionDoesNotFireOnEachApproval(): void
    {
        $this->requireIntermediateMethod();
        [$instanceId, $nodeId] = $this->buildSerialApprovalWorkflow();

        // Reject via the rejected output port
        $result = $this->engine->resumeWorkflow($instanceId, $nodeId, 'rejected', [
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'rejected',
            'comment' => 'Not approved',
        ]);

        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // on_each_approval must NOT have fired
        $this->assertCount(0, IntermediateActionTracker::$calls,
            'on_each_approval must not fire for rejections');

        // rejected port should reach end_nope
        $endNopeLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_nope'])
            ->first();
        $this->assertNotNull($endNopeLog, 'end_nope should be reached via rejected port');

        // end_ok must NOT be reached
        $endOkLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_ok'])
            ->first();
        $this->assertNull($endOkLog, 'end_ok must not be reached on rejection');
    }

    // =====================================================
    // Test 5: Context injection — approval data available
    //         to intermediate action targets
    // =====================================================

    public function testIntermediateApprovalContextInjection(): void
    {
        $this->requireIntermediateMethod();
        [$instanceId, $nodeId] = $this->buildSerialApprovalWorkflow();

        $approvalData = [
            'approvedCount' => 2,
            'requiredCount' => 3,
            'approverId' => self::TEST_MEMBER_AGATHA_ID,
            'nextApproverId' => self::TEST_MEMBER_BRYCE_ID,
            'approvalChain' => [
                ['approver_id' => self::ADMIN_MEMBER_ID, 'responded_at' => '2026-01-01T00:00:00+00:00'],
                ['approver_id' => self::TEST_MEMBER_AGATHA_ID, 'responded_at' => '2026-01-02T00:00:00+00:00'],
            ],
            'decision' => 'approve',
        ];

        $result = $this->engine->fireIntermediateApprovalActions($instanceId, $nodeId, $approvalData);
        $this->assertTrue($result->isSuccess());

        // Verify context was injected into the persisted instance
        $instance = $this->instancesTable->get($instanceId);
        $nodeContext = $instance->context['nodes'][$nodeId] ?? null;

        $this->assertNotNull($nodeContext, 'Context must be populated for the approval node');
        $this->assertSame(2, $nodeContext['approvedCount'] ?? null,
            'approvedCount must be injected into node context');
        $this->assertSame(3, $nodeContext['requiredCount'] ?? null,
            'requiredCount must be injected into node context');
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $nodeContext['approverId'] ?? null,
            'approverId must be injected into node context');
        $this->assertSame('approve', $nodeContext['decision'] ?? null,
            'decision must be injected into node context');

        // Verify the action service received context with the injected data
        $this->assertCount(1, IntermediateActionTracker::$calls);
        $trackedContext = IntermediateActionTracker::$calls[0]['context'];
        $trackedNodeCtx = $trackedContext['nodes'][$nodeId] ?? null;

        $this->assertNotNull($trackedNodeCtx,
            'Action must receive context with approval node data');
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $trackedNodeCtx['approverId'] ?? null,
            'Action context must include approverId');
        $this->assertSame(2, $trackedNodeCtx['approvedCount'] ?? null,
            'Action context must include approvedCount');
        $this->assertSame(3, $trackedNodeCtx['requiredCount'] ?? null,
            'Action context must include requiredCount');
    }

    // =====================================================
    // Test 6: Single-approval gate — on_each_approval
    //         never fires because the only approval is final
    // =====================================================

    public function testSingleApprovalGateOnEachApprovalNeverFires(): void
    {
        $this->requireIntermediateMethod();

        $slug = 'single-gate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'approve1']],
                ],
                'approve1' => [
                    'type' => 'approval',
                    'config' => ['approverType' => 'permission', 'requiredCount' => 1],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_ok'],
                        ['port' => 'rejected', 'target' => 'end_nope'],
                        ['port' => 'on_each_approval', 'target' => 'notify_action'],
                    ],
                ],
                'notify_action' => [
                    'type' => 'action',
                    'config' => ['action' => 'TestIntermediate.Track'],
                    'outputs' => [],
                ],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_nope' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        // With requiredCount=1, the only approval IS the final one.
        // Use resumeWorkflow directly — never call fireIntermediateApprovalActions.
        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'approve1', 'approved', [
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'approved',
        ]);

        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status,
            'Single-approval workflow should complete');

        // on_each_approval must never fire — the only approval is the final one
        $this->assertCount(0, IntermediateActionTracker::$calls,
            'on_each_approval should never fire for a single-approval gate');

        // approved port should have completed via end_ok
        $endOkLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_ok'])
            ->first();
        $this->assertNotNull($endOkLog, 'end_ok should be executed on final approval');
    }
}
