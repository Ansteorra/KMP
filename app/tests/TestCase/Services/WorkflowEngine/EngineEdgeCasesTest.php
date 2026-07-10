<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\ExpressionEvaluator;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;
use DateTime;
use DateTimeInterface;

/**
 * Edge-case and boundary-condition tests for the workflow engine.
 *
 * Covers action, condition, loop, delay, end, fork/join node edge cases,
 * context management, expression evaluator boundaries, and lifecycle states.
 */
class EngineEdgeCasesTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private $defTable;
    private $versionsTable;
    private $instancesTable;
    private $logsTable;
    private $approvalsTable;
    private ExpressionEvaluator $expr;

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
        $this->expr = new ExpressionEvaluator();
    }

    /**
     * Create an active workflow definition with a published version.
     */
    private function createWorkflow(string $slug, array $definition): array
    {
        $def = $this->defTable->newEntity([
            'name' => 'EdgeCase: ' . $slug,
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
     * Helper to start a simple trigger→end workflow and return the instance.
     */
    private function startSimpleWorkflow(array $triggerData = []): WorkflowInstance
    {
        $slug = 'simple-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, $triggerData);
        $this->assertTrue($result->isSuccess());

        return $this->instancesTable->get($result->data['instanceId']);
    }

    // =========================================================================
    // 1-5: ACTION NODE EDGE CASES
    // =========================================================================

    /** 1. Action node with null config (no 'config' key at all). */
    public function testActionNodeWithNullConfigThrowsError(): void
    {
        $slug = 'act-null-cfg-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'action1'],
                ]],
                'action1' => ['type' => 'action', 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 2. Action node with empty config (no action name). */
    public function testActionNodeWithEmptyConfigThrowsError(): void
    {
        $slug = 'act-empty-cfg-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'action1'],
                ]],
                'action1' => ['type' => 'action', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 3. Action node with missing action name key. */
    public function testActionNodeMissingActionNameFails(): void
    {
        $slug = 'act-no-name-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'action1'],
                ]],
                'action1' => ['type' => 'action', 'config' => ['params' => ['key' => 'val']], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 4. Action node referencing a non-existent action in the registry. */
    public function testActionNodeUnregisteredActionFails(): void
    {
        $slug = 'act-unreg-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'action1'],
                ]],
                'action1' => ['type' => 'action', 'config' => [
                    'action' => 'totally_nonexistent_action_' . uniqid(),
                ], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 5. Action node with config that has null action value. */
    public function testActionNodeWithNullActionValueFails(): void
    {
        $slug = 'act-null-val-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'action1'],
                ]],
                'action1' => ['type' => 'action', 'config' => [
                    'action' => null,
                ], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    // =========================================================================
    // 6-8: CONDITION NODE EDGE CASES
    // =========================================================================

    /** 6. Condition node missing true port target: true eval has nowhere to go, false leads to end. */
    public function testConditionNodeMissingTrueOutputFollowsFalseWhenFalse(): void
    {
        $slug = 'cond-no-true-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.status == active',
                ], 'outputs' => [
                    // Only false port
                    ['port' => 'false', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Trigger with status=inactive → false path → end1
        $result = $this->engine->startWorkflow($slug, ['status' => 'inactive']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    /** 7. Condition node with numeric comparison (greater-than). */
    public function testConditionNodeWithNumericComparisonFollowsTrue(): void
    {
        $slug = 'cond-numeric-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.count > 0',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_true'],
                    ['port' => 'false', 'target' => 'end_false'],
                ]],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['count' => 5]);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $trueLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_true',
        ])->first();
        $this->assertNotNull($trueLog);
    }

    /** 8. Condition node resolves deeply nested context path. */
    public function testConditionNodeWithNestedContextPath(): void
    {
        $slug = 'cond-nested-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.data.level1.level2 == deep',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_true'],
                    ['port' => 'false', 'target' => 'end_false'],
                ]],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'data' => ['level1' => ['level2' => 'deep']],
        ]);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $trueLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_true',
        ])->first();
        $this->assertNotNull($trueLog);
    }

    // =========================================================================
    // 9-11: LOOP NODE EDGE CASES
    // =========================================================================

    /** 9. Loop node with maxIterations=1 exits immediately after one pass. */
    public function testLoopNodeSingleIterationExits(): void
    {
        $slug = 'loop-one-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'loop1'],
                ]],
                'loop1' => ['type' => 'loop', 'config' => ['maxIterations' => 1], 'outputs' => [
                    ['port' => 'exit', 'target' => 'end1'],
                    ['port' => 'continue', 'target' => 'loop1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    /** 10. Loop node cycle detection: max execution depth prevents infinite loops. */
    public function testLoopNodeExceedingMaxDepthFailsInstance(): void
    {
        $slug = 'loop-deep-' . uniqid();
        // maxIterations very high, will hit cycle detection (MAX_EXECUTION_DEPTH=200)
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'loop1'],
                ]],
                'loop1' => ['type' => 'loop', 'config' => ['maxIterations' => 500], 'outputs' => [
                    ['port' => 'exit', 'target' => 'end1'],
                    ['port' => 'continue', 'target' => 'loop1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 11. Loop node stores iteration count in context correctly. */
    public function testLoopNodeStoresIterationInContext(): void
    {
        $slug = 'loop-ctx-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'loop1'],
                ]],
                'loop1' => ['type' => 'loop', 'config' => ['maxIterations' => 3], 'outputs' => [
                    ['port' => 'exit', 'target' => 'end1'],
                    ['port' => 'continue', 'target' => 'loop1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $loopState = $instance->context['_internal']['loopState']['loop1'] ?? null;
        $this->assertNotNull($loopState);
        $this->assertEquals(3, $loopState['iteration']);
    }

    // =========================================================================
    // 12-14: DELAY NODE EDGE CASES
    // =========================================================================

    /** 12. Delay node with zero delay still sets instance to waiting. */
    public function testDelayNodeWithZeroDelaySetsWaiting(): void
    {
        $slug = 'delay-zero-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => 0], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_WAITING, $instance->status);
    }

    /** 13. Delay node with empty config still pauses execution. */
    public function testDelayNodeWithEmptyConfigSetsWaiting(): void
    {
        $slug = 'delay-empty-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_WAITING, $instance->status);
    }

    /** 14. Delay node stores config in execution log output_data. */
    public function testDelayNodeStoresConfigInLog(): void
    {
        $slug = 'delay-log-' . uniqid();
        $delayConfig = ['duration' => '24h', 'unit' => 'hours'];
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => $delayConfig, 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $log = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
            'node_id' => 'delay1',
        ])->first();
        $this->assertNotNull($log);
        $this->assertEquals(WorkflowExecutionLog::STATUS_WAITING, $log->status);
        $this->assertEquals(['delayConfig' => $delayConfig], $log->output_data);
    }

    // =========================================================================
    // 15-17: END NODE EDGE CASES
    // =========================================================================

    /** 15. Multiple end nodes: condition routes to correct one. */
    public function testMultipleEndNodesConditionRoutesCorrectly(): void
    {
        $slug = 'multi-end-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.status == active',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_a'],
                    ['port' => 'false', 'target' => 'end_b'],
                ]],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'active']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // end_a should be reached (true path), end_b should not
        $logA = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_a',
        ])->first();
        $logB = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_b',
        ])->first();
        $this->assertNotNull($logA);
        $this->assertNull($logB);
    }

    /** 16. End node marks completion timestamp. */
    public function testEndNodeSetsCompletionTimestamp(): void
    {
        $slug = 'end-time-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertNotNull($instance->completed_at);
    }

    /** 17. Unreachable end node is never executed. */
    public function testUnreachableEndNodeNeverExecuted(): void
    {
        $slug = 'unreach-end-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_orphan' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $orphanLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
            'node_id' => 'end_orphan',
        ])->first();
        $this->assertNull($orphanLog);
    }

    // =========================================================================
    // 18-20: FORK/JOIN EDGE CASES
    // =========================================================================

    /** 18. Fork node with zero branches (no outputs) completes without advancing. */
    public function testForkWithZeroBranchesCompletes(): void
    {
        $slug = 'fork-zero-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => []],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        // Fork executes but has no outputs, so no end node is reached
        $forkLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'fork1',
        ])->first();
        $this->assertNotNull($forkLog);
        $this->assertEquals(WorkflowExecutionLog::STATUS_COMPLETED, $forkLog->status);
    }

    /** 19. Fork with single branch executes that branch. */
    public function testForkWithSingleBranch(): void
    {
        $slug = 'fork-one-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => [
                    ['port' => 'branch1', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    /** 20. Join waits for all fork branches before advancing. */
    public function testJoinWaitsForAllForkBranches(): void
    {
        $slug = 'join-wait-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => [
                    ['port' => 'branch1', 'target' => 'delay1'],
                    ['port' => 'branch2', 'target' => 'join1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1h'], 'outputs' => [
                    ['port' => 'default', 'target' => 'join1'],
                ]],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'delay1', 'sourcePort' => 'branch1'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'branch2'],
                ['source' => 'delay1', 'target' => 'join1', 'sourcePort' => 'default'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        // Should be waiting because one branch has a delay
        $this->assertEquals(WorkflowInstance::STATUS_WAITING, $instance->status);
    }

    // =========================================================================
    // 21-23: CONTEXT PATH RESOLUTION
    // =========================================================================

    /** 21. Expression evaluator resolves 5+ levels deep context paths. */
    public function testContextPathDeeplyNested(): void
    {
        $context = [
            'a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'found']]]]],
        ];
        $result = $this->expr->evaluate('$.a.b.c.d.e.f', $context);
        $this->assertEquals('found', $result);
    }

    /** 22. Expression evaluator returns null for missing intermediate path. */
    public function testContextPathMissingIntermediateReturnsNull(): void
    {
        $context = ['a' => ['b' => 'leaf']];
        $result = $this->expr->evaluate('$.a.b.c.d', $context);
        $this->assertNull($result);
    }

    /** 23. Expression evaluator handles array index-like numeric keys. */
    public function testContextPathWithNumericKeys(): void
    {
        $context = [
            'items' => ['first', 'second', 'third'],
        ];
        $result = $this->expr->evaluate('$.items.0', $context);
        $this->assertEquals('first', $result);
    }

    // =========================================================================
    // 24-25: CONTEXT IMMUTABILITY IN FORKS
    // =========================================================================

    /** 24. Fork branches share the same context object (sequential execution). */
    public function testForkBranchesShareContext(): void
    {
        $slug = 'fork-shared-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => [
                    ['port' => 'branch1', 'target' => 'end1'],
                    ['port' => 'branch2', 'target' => 'end2'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end2' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['shared' => 'data']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals('data', $instance->context['trigger']['shared']);
    }

    /** 25. Trigger data persists through fork execution. */
    public function testForkPreservesTriggerContext(): void
    {
        $slug = 'fork-trig-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => [
                    ['port' => 'branch1', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $triggerData = ['entity_id' => 42, 'type' => 'award', 'nested' => ['key' => 'val']];
        $result = $this->engine->startWorkflow($slug, $triggerData);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals($triggerData, $instance->context['trigger']);
    }

    // =========================================================================
    // 26-27: CONTEXT SIZE AND DATA TYPES
    // =========================================================================

    /** 26. Large context object is preserved through workflow execution. */
    public function testLargeContextObjectPreserved(): void
    {
        $largeData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeData["key_{$i}"] = str_repeat("value_{$i}", 10);
        }

        $slug = 'large-ctx-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, $largeData);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals($largeData, $instance->context['trigger']);
    }

    /** 27. Special characters in context are preserved. */
    public function testSpecialCharactersInContextPreserved(): void
    {
        $data = [
            'name' => "O'Reilly & Sons <Ltd>",
            'emoji' => '🎭🏰⚔️',
            'newlines' => "line1\nline2\ttab",
            'unicode' => 'Ælfrēd þe Grēat',
        ];

        $slug = 'special-ctx-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, $data);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals($data, $instance->context['trigger']);
    }

    // =========================================================================
    // 28-30: EXPRESSION EVALUATOR — EMPTY, NULL, DIVISION
    // =========================================================================

    /** 28. Empty expression returns null. */
    public function testExpressionEmptyReturnsNull(): void
    {
        $this->assertNull($this->expr->evaluate('', []));
    }

    /** 29. Null values in expression context return null for path resolution. */
    public function testExpressionNullContextValueReturnsNull(): void
    {
        $result = $this->expr->evaluate('$.missing.path', ['other' => 'data']);
        $this->assertNull($result);
    }

    /** 30. Division by zero returns 0. */
    public function testExpressionDivisionByZeroReturnsZero(): void
    {
        $result = $this->expr->evaluate('10 / 0', []);
        $this->assertEquals(0, $result);
    }

    // =========================================================================
    // 31-33: DATE EXPRESSION EDGE CASES
    // =========================================================================

    /** 31. Date expression with unrecognized format returns null. */
    public function testDateExpressionInvalidFormatReturnsNull(): void
    {
        $result = $this->expr->evaluateDateExpression('not-a-date', []);
        $this->assertNull($result);
    }

    /** 32. Date expression 'now' returns a DateTime. */
    public function testDateExpressionNowReturnsDateTime(): void
    {
        $result = $this->expr->evaluateDateExpression('now', []);
        $this->assertInstanceOf(DateTimeInterface::class, $result);
    }

    /** 33. Date diff: adding then subtracting the same amount returns approximately the same date. */
    public function testDateExpressionAddSubtractSymmetry(): void
    {
        $context = ['start' => '2025-06-15'];
        $added = $this->expr->evaluateDateExpression('$.start + 30 days', $context);
        $this->assertInstanceOf(DateTimeInterface::class, $added);
        $this->assertEquals('2025-07-15', $added->format('Y-m-d'));

        $context2 = ['start' => '2025-07-15'];
        $subtracted = $this->expr->evaluateDateExpression('$.start - 30 days', $context2);
        $this->assertInstanceOf(DateTimeInterface::class, $subtracted);
        $this->assertEquals('2025-06-15', $subtracted->format('Y-m-d'));
    }

    // =========================================================================
    // 34-35: TERNARY EXPRESSION EDGE CASES
    // =========================================================================

    /** 34. Nested ternary — only outer level is parsed. */
    public function testTernaryWithQuotedColonInValue(): void
    {
        // Value contains a colon inside quotes — should not split there
        $result = $this->expr->evaluateConditional("1 == 1 ? 'yes:sir' : 'no'", []);
        $this->assertEquals('yes:sir', $result);
    }

    /** 35. Ternary with complex context condition. */
    public function testTernaryWithContextPathCondition(): void
    {
        $context = ['trigger' => ['status' => 'active']];
        $result = $this->expr->evaluate("$.trigger.status == 'active' ? 'go' : 'stop'", $context);
        $this->assertEquals('go', $result);
    }

    // =========================================================================
    // 36-37: WORKFLOW LIFECYCLE — START/RESUME EDGE CASES
    // =========================================================================

    /** 36. Starting a workflow that already has a running instance for same entity fails/deduplicates. */
    public function testStartDuplicateWorkflowForSameEntityPrevented(): void
    {
        $slug = 'dup-start-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1h'], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // First start succeeds
        $result1 = $this->engine->startWorkflow($slug, [], null, 'Award', 99);
        $this->assertTrue($result1->isSuccess());

        // Second start for same entity should be prevented
        $result2 = $this->engine->startWorkflow($slug, [], null, 'Award', 99);
        $this->assertFalse($result2->isSuccess());
    }

    /** 37. Resuming a completed workflow fails. */
    public function testResumeCompletedWorkflowFails(): void
    {
        $slug = 'resume-done-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $this->assertTrue($startResult->isSuccess());
        $instanceId = $startResult->data['instanceId'];

        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'end1', 'default');
        $this->assertFalse($resumeResult->isSuccess());
    }

    // =========================================================================
    // 38-39: CANCEL WORKFLOW IN VARIOUS STATES
    // =========================================================================

    /** 38. Cancel a waiting workflow succeeds. */
    public function testCancelWaitingWorkflowSucceeds(): void
    {
        $slug = 'cancel-wait-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1h'], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $cancelResult = $this->engine->cancelWorkflow($instanceId, 'Testing cancel');
        $this->assertTrue($cancelResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertEquals(WorkflowInstance::STATUS_CANCELLED, $instance->status);
    }

    /** 39. Cancel an already-completed workflow fails. */
    public function testCancelCompletedWorkflowFails(): void
    {
        $slug = 'cancel-done-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        $cancelResult = $this->engine->cancelWorkflow($instanceId);
        $this->assertFalse($cancelResult->isSuccess());
    }

    // =========================================================================
    // 40: CONCURRENT START CALLS
    // =========================================================================

    /** 40. Second startWorkflow for same entity/definition is rejected. */
    public function testConcurrentStartSameDefinitionSameEntityRejected(): void
    {
        $slug = 'concurrent-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $r1 = $this->engine->startWorkflow($slug, [], 1, 'Member', 42);
        $this->assertTrue($r1->isSuccess());

        $r2 = $this->engine->startWorkflow($slug, [], 1, 'Member', 42);
        $this->assertFalse($r2->isSuccess());
        $this->assertStringContainsString('already', strtolower($r2->reason));
    }

    // =========================================================================
    // ADDITIONAL EDGE CASES (41-55)
    // =========================================================================

    /** 41. Unknown node types fail workflow startup. */
    public function testUnknownNodeTypeFailsWorkflow(): void
    {
        $slug = 'unknown-type-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'mystery1'],
                ]],
                'mystery1' => ['type' => 'nonexistent_type', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Unknown node type', $result->reason);
    }

    /** 42. Workflow with only trigger and no other nodes. */
    public function testWorkflowWithOnlyTriggerNodeNoOutputs(): void
    {
        $slug = 'trig-only-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
    }

    /** 43. Workflow with empty nodes array fails. */
    public function testWorkflowWithEmptyNodesFails(): void
    {
        $slug = 'empty-nodes-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertFalse($result->isSuccess());
    }

    /** 44. Expression evaluator: whitespace-only expression returns null. */
    public function testExpressionWhitespaceOnlyReturnsNull(): void
    {
        $this->assertNull($this->expr->evaluate('   ', []));
    }

    /** 45. Expression evaluator: arithmetic with both context paths. */
    public function testArithmeticBothContextPaths(): void
    {
        $context = ['a' => 10, 'b' => 3];
        $result = $this->expr->evaluate('$.a + $.b', $context);
        $this->assertEquals(13, $result);
    }

    /** 46. Expression evaluator: arithmetic subtraction with negative result. */
    public function testArithmeticNegativeResult(): void
    {
        $result = $this->expr->evaluate('3 - 10', []);
        $this->assertEquals(-7, $result);
    }

    /** 47. Expression evaluator: multiplication by zero. */
    public function testArithmeticMultiplicationByZero(): void
    {
        $result = $this->expr->evaluate('42 * 0', []);
        $this->assertEquals(0, $result);
    }

    /** 48. Expression evaluator: string template with multiple consecutive placeholders. */
    public function testTemplateConsecutivePlaceholders(): void
    {
        $context = ['first' => 'Hello', 'second' => 'World'];
        $result = $this->expr->evaluate('{{$.first}}{{$.second}}', $context);
        $this->assertEquals('HelloWorld', $result);
    }

    /** 49. Expression evaluator: context path to boolean value. */
    public function testContextPathBooleanValue(): void
    {
        $context = ['flag' => true];
        $result = $this->expr->evaluate('$.flag', $context);
        $this->assertTrue($result);
    }

    /** 50. Expression evaluator: ternary false path selected. */
    public function testTernaryFalsePathSelected(): void
    {
        $context = ['status' => 'inactive'];
        $result = $this->expr->evaluate("$.status == 'active' ? 'yes' : 'no'", $context);
        $this->assertEquals('no', $result);
    }

    /** 51. Cancel workflow stores reason in error_info. */
    public function testCancelWorkflowStoresReason(): void
    {
        $slug = 'cancel-reason-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $reason = 'Administrator cancelled this workflow';
        $this->engine->cancelWorkflow($result->data['instanceId'], $reason);

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_CANCELLED, $instance->status);
        $this->assertStringContainsString($reason, $instance->error_info['cancellation_reason'] ?? '');
    }

    /** 52. Condition node with false expression follows false port. */
    public function testConditionFalsePathEndToEnd(): void
    {
        $slug = 'cond-false-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.status == active',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_true'],
                    ['port' => 'false', 'target' => 'end_false'],
                ]],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'inactive']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $falseLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_false',
        ])->first();
        $this->assertNotNull($falseLog);
    }

    /** 53. getInstanceState returns null for non-existent instance. */
    public function testGetInstanceStateNonExistentReturnsNull(): void
    {
        $state = $this->engine->getInstanceState(999999);
        $this->assertNull($state);
    }

    /** 54. getInstanceState returns full state for a valid instance. */
    public function testGetInstanceStateReturnsData(): void
    {
        $slug = 'state-check-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['key' => 'value']);
        $this->assertTrue($result->isSuccess());

        $state = $this->engine->getInstanceState($result->data['instanceId']);
        $this->assertNotNull($state);
        $this->assertArrayHasKey('status', $state);
    }

    /** 55. Loop with exit condition based on trigger data exits early. */
    public function testLoopExitConditionFromTriggerData(): void
    {
        $slug = 'loop-exit-imm-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'loop1'],
                ]],
                'loop1' => ['type' => 'loop', 'config' => [
                    'maxIterations' => 100,
                    'exitCondition' => 'trigger.shouldExit == yes',
                ], 'outputs' => [
                    ['port' => 'exit', 'target' => 'end1'],
                    ['port' => 'continue', 'target' => 'loop1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['shouldExit' => 'yes']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        // Should have exited after first iteration due to exit condition
        $loopState = $instance->context['_internal']['loopState']['loop1'] ?? [];
        $this->assertEquals(1, $loopState['iteration'] ?? 0);
    }

    /** 56. Date expression: adding weeks. */
    public function testDateExpressionAddWeeks(): void
    {
        $context = ['start' => '2025-01-01'];
        $result = $this->expr->evaluateDateExpression('$.start + 2 weeks', $context);
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertEquals('2025-01-15', $result->format('Y-m-d'));
    }

    /** 57. Date expression: adding hours. */
    public function testDateExpressionAddHours(): void
    {
        $result = $this->expr->evaluateDateExpression('now + 24 hours', []);
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $tomorrow = (new DateTime())->modify('+24 hours');
        $this->assertEquals($tomorrow->format('Y-m-d'), $result->format('Y-m-d'));
    }

    /** 58. Date expression: singular unit 'day' works. */
    public function testDateExpressionSingularUnit(): void
    {
        $context = ['d' => '2025-03-01'];
        $result = $this->expr->evaluateDateExpression('$.d + 1 day', $context);
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertEquals('2025-03-02', $result->format('Y-m-d'));
    }

    /** 59. String concatenation with context paths. */
    public function testStringConcatenationContextPaths(): void
    {
        $context = ['first' => 'John', 'last' => 'Doe'];
        $result = $this->expr->evaluate("$.first . ' ' . $.last", $context);
        $this->assertEquals('John Doe', $result);
    }

    /** 60. Expression evaluator: literal string returned as-is. */
    public function testExpressionLiteralStringPassthrough(): void
    {
        $result = $this->expr->evaluate('hello world', []);
        $this->assertEquals('hello world', $result);
    }

    /** 61. Condition node stores result and port in context. */
    public function testConditionNodeStoresResultInContext(): void
    {
        $slug = 'cond-ctx-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.status == active',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end1'],
                    ['port' => 'false', 'target' => 'end2'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end2' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'active']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $condResult = $instance->context['nodes']['cond1'] ?? null;
        $this->assertNotNull($condResult);
        $this->assertTrue($condResult['result']);
        $this->assertEquals('true', $condResult['port']);
    }

    /** 62. Resume a delay node advances workflow to completion. */
    public function testResumeDelayNodeCompletesWorkflow(): void
    {
        $slug = 'resume-delay-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1h'], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $this->assertTrue($startResult->isSuccess());
        $instanceId = $startResult->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertEquals(WorkflowInstance::STATUS_WAITING, $instance->status);

        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'delay1', 'default');
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    /** 63. Cancel already-cancelled workflow fails. */
    public function testCancelAlreadyCancelledWorkflowFails(): void
    {
        $slug = 'cancel-twice-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $instanceId = $result->data['instanceId'];

        $cancel1 = $this->engine->cancelWorkflow($instanceId);
        $this->assertTrue($cancel1->isSuccess());

        $cancel2 = $this->engine->cancelWorkflow($instanceId);
        $this->assertFalse($cancel2->isSuccess());
    }

    /** 64. Execution log records the correct node_type. */
    public function testExecutionLogRecordsCorrectNodeType(): void
    {
        $slug = 'log-type-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'delay1'],
                ]],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $delayLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
            'node_id' => 'delay1',
        ])->first();
        $this->assertNotNull($delayLog);
        $this->assertEquals('delay', $delayLog->node_type);
    }

    /** 65. Trigger node is logged as COMPLETED in execution logs. */
    public function testTriggerNodeLoggedAsCompleted(): void
    {
        $slug = 'trig-log-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $trigLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
            'node_id' => 'trigger1',
        ])->first();
        $this->assertNotNull($trigLog);
        $this->assertEquals(WorkflowExecutionLog::STATUS_COMPLETED, $trigLog->status);
    }

    /** 66. Arithmetic with float result. */
    public function testArithmeticFloatResult(): void
    {
        $result = $this->expr->evaluate('7 / 2', []);
        $this->assertEquals(3.5, $result);
    }

    /** 67. Arithmetic integer result from float division. */
    public function testArithmeticIntegerResultFromDivision(): void
    {
        $result = $this->expr->evaluate('10 / 2', []);
        $this->assertIsInt($result);
        $this->assertEquals(5, $result);
    }

    /** 68. Template with non-scalar context value replaced with empty string. */
    public function testTemplateNonScalarReplacedWithEmpty(): void
    {
        $context = ['arr' => [1, 2, 3]];
        $result = $this->expr->evaluate('Value: {{$.arr}}', $context);
        $this->assertEquals('Value: ', $result);
    }

    /** 69. Condition with missing context path evaluates false path. */
    public function testConditionMissingContextFollowsFalse(): void
    {
        $slug = 'cond-missing-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.nonexistent == value',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_true'],
                    ['port' => 'false', 'target' => 'end_false'],
                ]],
                'end_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, []);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $falseLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_false',
        ])->first();
        $this->assertNotNull($falseLog);
    }

    /** 70. Chained conditions: true → true. */
    public function testChainedConditionsTrueTrue(): void
    {
        $slug = 'chain-cond-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.step == go',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'cond2'],
                    ['port' => 'false', 'target' => 'end_fail'],
                ]],
                'cond2' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.level > 0',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end_ok'],
                    ['port' => 'false', 'target' => 'end_fail'],
                ]],
                'end_ok' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_fail' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['step' => 'go', 'level' => 5]);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $okLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'end_ok',
        ])->first();
        $this->assertNotNull($okLog);
    }

    /** 71. Cycle detection: non-loop node visiting itself fails. */
    public function testCycleDetectionNonLoopNodeFails(): void
    {
        $slug = 'cycle-detect-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.status == active',
                ], 'outputs' => [
                    // True points back to cond1 → cycle
                    ['port' => 'true', 'target' => 'cond1'],
                    ['port' => 'false', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['status' => 'active']);
        $this->assertFalse($result->isSuccess());
    }

    /** 72. Loop default maxIterations is 10 when not specified. */
    public function testLoopDefaultMaxIterationsIsTen(): void
    {
        $slug = 'loop-default-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'loop1'],
                ]],
                'loop1' => ['type' => 'loop', 'config' => [], 'outputs' => [
                    ['port' => 'exit', 'target' => 'end1'],
                    ['port' => 'continue', 'target' => 'loop1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $loopState = $instance->context['_internal']['loopState']['loop1'] ?? [];
        $this->assertEquals(10, $loopState['iteration'] ?? 0);
    }

    /** 73. Resuming non-existent instance fails. */
    public function testResumeNonExistentInstanceFails(): void
    {
        $result = $this->engine->resumeWorkflow(999999, 'node1', 'default');
        $this->assertFalse($result->isSuccess());
    }

    /** 74. Start workflow with startedBy member ID stored in context. */
    public function testStartWorkflowStartedByStoredInContext(): void
    {
        $slug = 'started-by-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['x' => 1], self::ADMIN_MEMBER_ID);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $instance->context['triggeredBy']);
    }

    /** 75. Start workflow with entity type and ID stored on instance. */
    public function testStartWorkflowEntityInfoStored(): void
    {
        $slug = 'entity-info-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [], 1, 'Award', 123);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals('Award', $instance->entity_type);
        $this->assertEquals(123, $instance->entity_id);
    }

    /** 76. Dispatchng trigger with no matching workflows returns empty. */
    public function testDispatchTriggerNoMatchReturnsEmpty(): void
    {
        $results = $this->engine->dispatchTrigger('nonexistent_event_' . uniqid());
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** 77. Expression: greater-than comparison. */
    public function testExpressionGreaterThan(): void
    {
        $context = ['val' => 15];
        $result = $this->expr->evaluate('$.val > 10 ? 100 : 0', $context);
        $this->assertEquals(100, $result);
    }

    /** 78. Expression: less-than comparison. */
    public function testExpressionLessThan(): void
    {
        $context = ['val' => 5];
        $result = $this->expr->evaluate('$.val < 10 ? 100 : 0', $context);
        $this->assertEquals(100, $result);
    }

    /** 79. Expression: greater-equal comparison. */
    public function testExpressionGreaterEqual(): void
    {
        $context = ['val' => 10];
        $result = $this->expr->evaluate('$.val >= 10 ? 100 : 0', $context);
        $this->assertEquals(100, $result);
    }

    /** 80. Expression: less-equal comparison. */
    public function testExpressionLessEqual(): void
    {
        $context = ['val' => 10];
        $result = $this->expr->evaluate('$.val <= 10 ? 100 : 0', $context);
        $this->assertEquals(100, $result);
    }

    /** 81. Expression: not-equals comparison. */
    public function testExpressionNotEquals(): void
    {
        $context = ['val' => 'active'];
        $result = $this->expr->evaluate("$.val != 'inactive' ? 'yes' : 'no'", $context);
        $this->assertEquals('yes', $result);
    }

    /** 82. Date expression with DateTime object in context. */
    public function testDateExpressionWithDateTimeObjectInContext(): void
    {
        $context = ['start' => new DateTime('2025-01-01')];
        $result = $this->expr->evaluateDateExpression('$.start + 10 days', $context);
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertEquals('2025-01-11', $result->format('Y-m-d'));
    }

    /** 83. Date expression with missing context path returns null. */
    public function testDateExpressionMissingContextPathReturnsNull(): void
    {
        $result = $this->expr->evaluateDateExpression('$.nonexistent + 5 days', []);
        $this->assertNull($result);
    }

    /** 84. Fork node log is marked COMPLETED. */
    public function testForkNodeLogMarkedCompleted(): void
    {
        $slug = 'fork-log-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'fork1'],
                ]],
                'fork1' => ['type' => 'fork', 'config' => [], 'outputs' => [
                    ['port' => 'branch1', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $forkLog = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
            'node_id' => 'fork1',
        ])->first();
        $this->assertNotNull($forkLog);
        $this->assertEquals(WorkflowExecutionLog::STATUS_COMPLETED, $forkLog->status);
    }

    /** 85. Linear workflow with multiple intermediate nodes all log COMPLETED. */
    public function testLinearChainAllNodesComplete(): void
    {
        $slug = 'linear-chain-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [
                    ['port' => 'default', 'target' => 'cond1'],
                ]],
                'cond1' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.step == go',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'cond2'],
                    ['port' => 'false', 'target' => 'end1'],
                ]],
                'cond2' => ['type' => 'condition', 'config' => [
                    'expression' => 'trigger.level > 0',
                ], 'outputs' => [
                    ['port' => 'true', 'target' => 'end1'],
                    ['port' => 'false', 'target' => 'end1'],
                ]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['step' => 'go', 'level' => 5]);
        $this->assertTrue($result->isSuccess());

        $logs = $this->logsTable->find()->where([
            'workflow_instance_id' => $result->data['instanceId'],
        ])->all();

        foreach ($logs as $log) {
            $this->assertEquals(
                WorkflowExecutionLog::STATUS_COMPLETED,
                $log->status,
                "Node {$log->node_id} should be COMPLETED",
            );
        }
    }
}
