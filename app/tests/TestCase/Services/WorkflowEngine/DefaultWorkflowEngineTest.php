<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WorkflowEngine\Actions\CoreActions;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Integration tests for DefaultWorkflowEngine graph execution.
 */
class DefaultWorkflowEngineTest extends BaseTestCase
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

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->engine = new DefaultWorkflowEngine($container);

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $this->logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
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

    // =====================================================
    // startWorkflow()
    // =====================================================

    public function testStartWorkflowWithValidSlug(): void
    {
        $slug = 'start-valid-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['key' => 'val']);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->data['instanceId']);
    }

    public function testStartWorkflowWithInvalidSlug(): void
    {
        $result = $this->engine->startWorkflow('nonexistent-slug-' . uniqid());

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No active workflow', $result->reason);
    }

    public function testStartWorkflowWithInactiveWorkflow(): void
    {
        $slug = 'inactive-' . uniqid();
        $def = $this->defTable->newEntity([
            'name' => 'Inactive Test',
            'slug' => $slug,
            'trigger_type' => 'manual',
            'is_active' => false,
        ]);
        $this->defTable->saveOrFail($def);

        $result = $this->engine->startWorkflow($slug);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No active workflow', $result->reason);
    }

    public function testStartWorkflowStoresTriggerData(): void
    {
        $slug = 'trigger-data-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $triggerData = ['entity_id' => 42, 'action' => 'create'];
        $result = $this->engine->startWorkflow($slug, $triggerData, 1);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame($triggerData, $instance->context['trigger']);
        $this->assertSame(1, $instance->context['triggeredBy']);
    }

    public function testStartWorkflowReturnsEndNodeWorkflowResult(): void
    {
        $slug = 'end-result-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => [
                    'type' => 'end',
                    'config' => [
                        'result' => [
                            'recommendationId' => '$.trigger.recommendationId',
                            'status' => 'created',
                        ],
                    ],
                    'outputs' => [],
                ],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['recommendationId' => 42]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([
            'recommendationId' => 42,
            'status' => 'created',
        ], $result->data['workflowResult']);
    }

    public function testStartWorkflowNoNodes(): void
    {
        $slug = 'empty-' . uniqid();
        $this->createWorkflow($slug, ['nodes' => []]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('no nodes', $result->reason);
    }

    // =====================================================
    // Linear flow: trigger → end
    // =====================================================

    public function testLinearFlowCompletesInstance(): void
    {
        $slug = 'linear-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($instance->completed_at);
    }

    public function testLinearFlowCreatesExecutionLogs(): void
    {
        $slug = 'linear-logs-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        $logs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->order(['id' => 'ASC'])
            ->all()
            ->toArray();

        $this->assertCount(2, $logs);
        $this->assertSame('trigger', $logs[0]->node_type);
        $this->assertSame(WorkflowExecutionLog::STATUS_COMPLETED, $logs[0]->status);
        $this->assertSame('end', $logs[1]->node_type);
        $this->assertSame(WorkflowExecutionLog::STATUS_COMPLETED, $logs[1]->status);
    }

    // =====================================================
    // Branching: condition → true/false paths
    // =====================================================

    public function testConditionTruePathFollowed(): void
    {
        $slug = 'cond-true-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.status == active'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_true'],
                        ['port' => 'false', 'target' => 'end_false'],
                    ],
                ],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'active']);

        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify end_true was executed, not end_false
        $endTrueLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_true'])
            ->first();
        $this->assertNotNull($endTrueLog);

        $endFalseLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_false'])
            ->first();
        $this->assertNull($endFalseLog);
    }

    public function testConditionFalsePathFollowed(): void
    {
        $slug = 'cond-false-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'cond1']]],
                'cond1' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.status == active'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_true'],
                        ['port' => 'false', 'target' => 'end_false'],
                    ],
                ],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'inactive']);

        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $endFalseLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_false'])
            ->first();
        $this->assertNotNull($endFalseLog);

        $endTrueLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_true'])
            ->first();
        $this->assertNull($endTrueLog);
    }

    // =====================================================
    // Approval node pauses instance
    // =====================================================

    public function testApprovalNodeSetsWaiting(): void
    {
        $slug = 'approval-wait-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'requiredCount' => 1,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Verify approval record created
        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'approve1'])
            ->first();
        $this->assertNotNull($approval);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
    }

    public function testApprovalNodeCreatesWaitingLog(): void
    {
        $slug = 'approval-log-' . uniqid();
        $this->createWorkflow($slug, [
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

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        $log = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'approve1'])
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(WorkflowExecutionLog::STATUS_WAITING, $log->status);
    }

    // =====================================================
    // Delay node
    // =====================================================

    public function testDelayNodeSetsWaiting(): void
    {
        $slug = 'delay-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => [
                    'type' => 'delay',
                    'config' => ['duration' => '1d'],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
    }

    public function testDelayNodeCreatesWaitingLog(): void
    {
        $slug = 'delay-log-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => [
                    'type' => 'delay',
                    'config' => ['duration' => '1h'],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        $log = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => 'delay1'])
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(WorkflowExecutionLog::STATUS_WAITING, $log->status);
    }

    // =====================================================
    // End node
    // =====================================================

    public function testEndNodeCompletesInstance(): void
    {
        $slug = 'end-complete-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($instance->completed_at);
        $this->assertEmpty($instance->active_nodes);
    }

    // =====================================================
    // Fork → Join parallel paths
    // =====================================================

    public function testForkJoinParallelPaths(): void
    {
        $slug = 'fork-join-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'branch_a', 'target' => 'join1'],
                        ['port' => 'branch_b', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'branch_a'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'branch_b'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    public function testForkExecutesAllBranches(): void
    {
        $slug = 'fork-branches-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'end_a'],
                        ['port' => 'b', 'target' => 'end_b'],
                    ],
                ],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        $endALog = $this->logsTable->find()->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_a'])->first();
        $endBLog = $this->logsTable->find()->where(['workflow_instance_id' => $instanceId, 'node_id' => 'end_b'])->first();
        $this->assertNotNull($endALog);
        $this->assertNotNull($endBLog);
    }

    // =====================================================
    // Loop node
    // =====================================================

    public function testLoopIterationCountingExitsAtMax(): void
    {
        $slug = 'loop-max-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'loop1']]],
                'loop1' => [
                    'type' => 'loop',
                    'config' => ['maxIterations' => 3],
                    'outputs' => [
                        ['port' => 'continue', 'target' => 'loop1'],
                        ['port' => 'exit', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Should have 3 loop logs (iterations 1, 2, 3 — exit on 3)
        $loopLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'loop1'])
            ->all()
            ->toArray();
        $this->assertCount(3, $loopLogs);
    }

    public function testLoopExitCondition(): void
    {
        $slug = 'loop-exit-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'loop1']]],
                'loop1' => [
                    'type' => 'loop',
                    'config' => [
                        'maxIterations' => 100,
                        'exitCondition' => 'trigger.done == yes',
                    ],
                    'outputs' => [
                        ['port' => 'continue', 'target' => 'loop1'],
                        ['port' => 'exit', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // trigger.done == "yes" is true from the start, so loop exits on iteration 1
        $result = $this->engine->startWorkflow($slug, ['done' => 'yes']);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $loopLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'loop1'])
            ->all()
            ->toArray();
        $this->assertCount(1, $loopLogs);
    }

    public function testLoopDefaultMaxIterations(): void
    {
        $slug = 'loop-default-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'loop1']]],
                'loop1' => [
                    'type' => 'loop',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'continue', 'target' => 'loop1'],
                        ['port' => 'exit', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        // Default maxIterations is 10
        $loopLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId'], 'node_id' => 'loop1'])
            ->all()
            ->toArray();
        $this->assertCount(10, $loopLogs);
    }

    // =====================================================
    // cancelWorkflow()
    // =====================================================

    public function testCancelWorkflow(): void
    {
        $slug = 'cancel-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $cancelResult = $this->engine->cancelWorkflow($instanceId, 'Testing cancel');

        $this->assertTrue($cancelResult->isSuccess());
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $instance->status);
        $this->assertSame('Testing cancel', $instance->error_info['cancellation_reason']);
    }

    public function testCancelCompletedInstanceFails(): void
    {
        $slug = 'cancel-done-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $cancelResult = $this->engine->cancelWorkflow($instanceId);

        $this->assertFalse($cancelResult->isSuccess());
        $this->assertStringContainsString('terminal state', $cancelResult->reason);
    }

    public function testCancelCancelsPendingApprovals(): void
    {
        $slug = 'cancel-approval-' . uniqid();
        $this->createWorkflow($slug, [
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

        $this->engine->cancelWorkflow($instanceId);

        $approval = $this->approvalsTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $approval->status);
    }

    // =====================================================
    // resumeWorkflow()
    // =====================================================

    public function testResumeWorkflowFromDelay(): void
    {
        $slug = 'resume-delay-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'delay1', 'default');

        $this->assertTrue($resumeResult->isSuccess());
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    public function testResumeNonWaitingInstanceFails(): void
    {
        $slug = 'resume-fail-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'end1', 'default');

        $this->assertFalse($resumeResult->isSuccess());
        $this->assertStringContainsString('not in waiting', $resumeResult->reason);
    }

    // =====================================================
    // getInstanceState()
    // =====================================================

    public function testGetInstanceStateReturnsFullData(): void
    {
        $slug = 'state-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        $state = $this->engine->getInstanceState($instanceId);

        $this->assertNotNull($state);
        $this->assertSame($instanceId, $state['id']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $state['status']);
        $this->assertArrayHasKey('execution_logs', $state);
        $this->assertArrayHasKey('approvals', $state);
        $this->assertArrayHasKey('context', $state);
    }

    public function testGetInstanceStateNonExistentReturnsNull(): void
    {
        $state = $this->engine->getInstanceState(999999);

        $this->assertNull($state);
    }

    // =====================================================
    // Error propagation
    // =====================================================

    public function testUnknownNodeTypeFailsInstance(): void
    {
        $slug = 'unknown-type-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'bad1']]],
                'bad1' => ['type' => 'mystery', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Unknown node type', $result->reason);
    }

    public function testActionNodeMissingActionThrows(): void
    {
        $slug = 'action-missing-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'act1']]],
                'act1' => ['type' => 'action', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        // Engine catches thrown exception and wraps in ServiceResult
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('no action configured', $result->reason);
    }

    public function testActionNodeUnknownActionThrows(): void
    {
        $slug = 'action-unknown-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'act1']]],
                'act1' => ['type' => 'action', 'config' => ['action' => 'NonExistent.Action'], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->reason);
    }

    public function testActionContextUpdatesAreAvailableToDownstreamNodes(): void
    {
        $awm = $this->createMock(ActiveWindowManagerInterface::class);
        $actions = new CoreActions($awm);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(CoreActions::class)
            ->willReturn(true);
        $container->method('get')
            ->with(CoreActions::class)
            ->willReturn($actions);
        $engine = new DefaultWorkflowEngine($container);

        WorkflowActionRegistry::register('TestContextUpdates', [[
            'action' => 'Core.SetVariable',
            'label' => 'Assign to Variable',
            'description' => 'Assign a variable',
            'inputSchema' => [],
            'outputSchema' => [],
            'serviceClass' => CoreActions::class,
            'serviceMethod' => 'setVariable',
        ]]);

        $slug = 'action-context-updates-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'assign1']],
                ],
                'assign1' => [
                    'type' => 'action',
                    'config' => [
                        'action' => 'Core.SetVariable',
                        'name' => 'selectedMemberId',
                        'value' => '$.trigger.memberId',
                    ],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => [
                    'type' => 'end',
                    'config' => [
                        'result' => [
                            'memberId' => '$.variables.selectedMemberId',
                            'nodeValue' => '$.nodes.assign1.result.value',
                        ],
                    ],
                    'outputs' => [],
                ],
            ],
        ]);

        $result = $engine->startWorkflow($slug, ['memberId' => 42]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(42, $instance->context['variables']['selectedMemberId']);
        $this->assertArrayNotHasKey('_contextUpdates', $instance->context['nodes']['assign1']['result']);
        $this->assertSame([
            'memberId' => 42,
            'nodeValue' => 42,
        ], $result->data['workflowResult']);
    }

    // =====================================================
    // dispatchTrigger()
    // =====================================================

    public function testDispatchTriggerMatchesEventName(): void
    {
        $slug = 'dispatch-match-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => 'member.created'],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $results = $this->engine->dispatchTrigger('member.created', ['id' => 1]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
    }

    public function testDispatchTriggerNoMatchReturnsEmpty(): void
    {
        $results = $this->engine->dispatchTrigger('nonexistent.event.' . uniqid());

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testDispatchTriggerCanTargetOneWorkflowDefinition(): void
    {
        $eventName = 'schedule.targeted.' . uniqid();
        [$firstDefinitionId] = $this->createWorkflow('dispatch-first-' . uniqid(), [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);
        [$secondDefinitionId] = $this->createWorkflow('dispatch-second-' . uniqid(), [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => ['event' => $eventName],
                    'outputs' => [['target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $results = $this->engine->dispatchTrigger($eventName, [
            'workflowDefinitionId' => $secondDefinitionId,
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $instance = $this->instancesTable->get($results[0]->data['instanceId']);
        $this->assertSame($secondDefinitionId, $instance->workflow_definition_id);
        $this->assertNotSame($firstDefinitionId, $instance->workflow_definition_id);
    }

    // =====================================================
    // Edge format: both per-node outputs and top-level edges
    // =====================================================

    public function testTopLevelEdgesFormat(): void
    {
        $slug = 'edges-format-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => []],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'trigger1', 'target' => 'end1', 'sourcePort' => 'default'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    // =====================================================
    // resumeWorkflow() — approval node context population
    // =====================================================

    /**
     * Helper: create an approval workflow, start it, and return the paused instance ID.
     */
    private function createAndStartApprovalWorkflow(): array
    {
        $slug = 'resume-ctx-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'approve1']]],
                'approve1' => [
                    'type' => 'approval',
                    'config' => ['approverType' => 'permission', 'requiredCount' => 1],
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
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        return [$instanceId, 'approve1'];
    }

    public function testResumeApprovedPopulatesNodesContext(): void
    {
        [$instanceId, $nodeId] = $this->createAndStartApprovalWorkflow();

        $additionalData = [
            'approverId' => self::ADMIN_MEMBER_ID,
            'comment' => 'Looks good to me',
            'decision' => 'approved',
        ];

        $result = $this->engine->resumeWorkflow($instanceId, $nodeId, 'approved', $additionalData);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $nodesCtx = $instance->context['nodes'][$nodeId] ?? null;

        $this->assertNotNull($nodesCtx, 'nodes context must be populated for approval node');
        $this->assertSame('approved', $nodesCtx['status']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $nodesCtx['approverId']);
        $this->assertSame('Looks good to me', $nodesCtx['comment']);
        $this->assertSame('approved', $nodesCtx['decision']);
    }

    public function testResumeRejectedPopulatesNodesContext(): void
    {
        [$instanceId, $nodeId] = $this->createAndStartApprovalWorkflow();

        $additionalData = [
            'approverId' => self::TEST_MEMBER_AGATHA_ID,
            'comment' => 'Not acceptable',
            'decision' => 'rejected',
        ];

        $result = $this->engine->resumeWorkflow($instanceId, $nodeId, 'rejected', $additionalData);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $nodesCtx = $instance->context['nodes'][$nodeId] ?? null;

        $this->assertNotNull($nodesCtx, 'nodes context must be populated for rejected approval');
        $this->assertSame('rejected', $nodesCtx['status']);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $nodesCtx['approverId']);
        $this->assertSame('Not acceptable', $nodesCtx['comment']);
        $this->assertSame('Not acceptable', $nodesCtx['rejectionComment']);
        $this->assertSame('rejected', $nodesCtx['decision']);
    }

    public function testResumeApprovalStillPopulatesResumeData(): void
    {
        [$instanceId, $nodeId] = $this->createAndStartApprovalWorkflow();

        $additionalData = [
            'approverId' => self::ADMIN_MEMBER_ID,
            'comment' => 'Backward compat check',
            'decision' => 'approved',
        ];

        $result = $this->engine->resumeWorkflow($instanceId, $nodeId, 'approved', $additionalData);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($instanceId);

        // resumeData must still be populated for backward compatibility
        $this->assertArrayHasKey('resumeData', $instance->context);
        $this->assertSame(self::ADMIN_MEMBER_ID, $instance->context['resumeData']['approverId']);
        $this->assertSame('Backward compat check', $instance->context['resumeData']['comment']);
    }

    public function testResumeWithEmptyAdditionalDataPopulatesNullDefaults(): void
    {
        [$instanceId, $nodeId] = $this->createAndStartApprovalWorkflow();

        // Empty additionalData — must not crash, should populate with null defaults
        $result = $this->engine->resumeWorkflow($instanceId, $nodeId, 'approved', []);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $nodesCtx = $instance->context['nodes'][$nodeId] ?? null;

        $this->assertNotNull($nodesCtx, 'nodes context must exist even with empty additionalData');
        $this->assertSame('approved', $nodesCtx['status']);
        $this->assertNull($nodesCtx['approverId']);
        $this->assertNull($nodesCtx['comment']);
        $this->assertNull($nodesCtx['rejectionComment']);
        // decision falls back to outputPort when not in additionalData
        $this->assertSame('approved', $nodesCtx['decision']);
    }

    // =====================================================
    // stateMachine nodes
    // =====================================================

    public function testStateMachineNodeValidTransition(): void
    {
        $slug = 'sm-valid-' . uniqid();
        $this->createWorkflow($slug, [
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
                            'Open' => ['Draft', 'Active'],
                            'Closed' => ['Done'],
                        ],
                        'transitions' => [
                            'Draft' => ['Active'],
                            'Active' => ['Done'],
                        ],
                        'stateRules' => [],
                    ],
                    'outputs' => [
                        ['port' => 'on_transition', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'currentState' => 'Draft',
            'targetState' => 'Active',
        ]);

        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify the state machine stored its result in context
        $ctx = $instance->context;
        $this->assertTrue($ctx['nodes']['sm1']['result']['success']);
        $this->assertSame('Active', $ctx['nodes']['sm1']['result']['toState']);
        $this->assertSame('Open', $ctx['nodes']['sm1']['result']['toStatus']);
    }

    public function testStateMachineNodeInvalidTransition(): void
    {
        $slug = 'sm-invalid-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sm1']]],
                'sm1' => [
                    'type' => 'stateMachine',
                    'config' => [
                        'currentState' => '$.trigger.currentState',
                        'targetState' => '$.trigger.targetState',
                        'statuses' => ['Open' => ['Draft', 'Active'], 'Closed' => ['Done']],
                        'transitions' => ['Draft' => ['Active'], 'Active' => ['Done']],
                        'stateRules' => [],
                    ],
                    'outputs' => [
                        ['port' => 'on_invalid', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'currentState' => 'Draft',
            'targetState' => 'Done',
        ]);

        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify the invalid port was taken
        $ctx = $instance->context;
        $this->assertFalse($ctx['nodes']['sm1']['result']['success']);
        $this->assertSame('on_invalid', $ctx['nodes']['sm1']['port']);
    }

    public function testStateMachineNodeMissingStatesFiresOnInvalid(): void
    {
        $slug = 'sm-nostate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sm1']]],
                'sm1' => [
                    'type' => 'stateMachine',
                    'config' => [
                        'statuses' => [],
                        'transitions' => [],
                        'stateRules' => [],
                    ],
                    'outputs' => [
                        ['port' => 'on_invalid', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // No currentState/targetState in trigger
        $result = $this->engine->startWorkflow($slug, []);

        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];
        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }
}
