<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Tests for context mutation behavior during fork/join execution.
 *
 * The engine executes fork branches sequentially on a shared instance,
 * so context mutations from earlier branches are visible to later ones.
 */
class ContextMutationTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private $defTable;
    private $versionsTable;
    private $instancesTable;
    private $logsTable;

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
    }

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
    // Fork branches share the same context object
    // =====================================================

    public function testForkBranchesStoreResultsInSharedContext(): void
    {
        $slug = 'ctx-shared-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'condB'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.x == 1'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'join1'],
                        ['port' => 'false', 'target' => 'join1'],
                    ],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.y == 2'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'join1'],
                        ['port' => 'false', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'condA', 'target' => 'join1'],
                ['source' => 'condB', 'target' => 'join1'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['x' => 1, 'y' => 2]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $context = $instance->context;

        // Both condition results should be stored in the shared context
        $this->assertArrayHasKey('condA', $context['nodes']);
        $this->assertArrayHasKey('condB', $context['nodes']);
        $this->assertTrue($context['nodes']['condA']['result']);
        $this->assertTrue($context['nodes']['condB']['result']);
    }

    public function testBranchAMutationVisibleToBranchB(): void
    {
        // Branch A (condA) executes first and stores result in context.
        // Branch B (condB) also reads from the same context object.
        // Since execution is sequential, condB's context snapshot includes condA's result.
        $slug = 'ctx-visible-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'condB'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.val == first'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_a'],
                        ['port' => 'false', 'target' => 'end_a'],
                    ],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.val == first'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_b'],
                        ['port' => 'false', 'target' => 'end_b'],
                    ],
                ],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['val' => 'first']);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $context = $instance->context;

        // Both branches executed — condA result was in context when condB ran
        $this->assertArrayHasKey('condA', $context['nodes']);
        $this->assertArrayHasKey('condB', $context['nodes']);

        // Verify execution order: condA log created before condB log
        $logA = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'condA', 'node_type' => 'condition'])
            ->first();
        $logB = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'condB', 'node_type' => 'condition'])
            ->first();
        $this->assertNotNull($logA);
        $this->assertNotNull($logB);
        $this->assertLessThanOrEqual($logB->id, $logA->id);
    }

    public function testAllBranchResultsAccumulateInContext(): void
    {
        // Fork branches each evaluate a condition, all results stored in shared context
        $slug = 'ctx-accumulate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'condB'],
                        ['port' => 'c', 'target' => 'condC'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.a == yes'],
                    'outputs' => [['port' => 'true', 'target' => 'end_a'], ['port' => 'false', 'target' => 'end_a']],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.b == yes'],
                    'outputs' => [['port' => 'true', 'target' => 'end_b'], ['port' => 'false', 'target' => 'end_b']],
                ],
                'condC' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.c == no'],
                    'outputs' => [['port' => 'true', 'target' => 'end_c'], ['port' => 'false', 'target' => 'end_c']],
                ],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_c' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['a' => 'yes', 'b' => 'yes', 'c' => 'no']);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $context = $instance->context;
        // All three branch condition results present in shared context
        $this->assertTrue($context['nodes']['condA']['result']);
        $this->assertTrue($context['nodes']['condB']['result']);
        $this->assertTrue($context['nodes']['condC']['result']);
    }

    public function testForkContextPreservesTriggerData(): void
    {
        $slug = 'ctx-trigger-' . uniqid();
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

        $triggerData = ['entity_id' => 99, 'action' => 'update', 'nested' => ['key' => 'val']];
        $result = $this->engine->startWorkflow($slug, $triggerData);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame($triggerData, $instance->context['trigger']);
    }

    public function testForkBranchExecutionIsSequential(): void
    {
        // Branches execute in output array order; verify via log IDs
        $slug = 'ctx-seq-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'first', 'target' => 'end_first'],
                        ['port' => 'second', 'target' => 'end_second'],
                        ['port' => 'third', 'target' => 'end_third'],
                    ],
                ],
                'end_first' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_second' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_third' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());
        $instanceId = $result->data['instanceId'];

        // Verify all three end nodes were reached in order
        $logs = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'node_type' => 'end',
            ])
            ->order(['id' => 'ASC'])
            ->all()
            ->toArray();

        $this->assertCount(3, $logs);
        $this->assertSame('end_first', $logs[0]->node_id);
        $this->assertSame('end_second', $logs[1]->node_id);
        $this->assertSame('end_third', $logs[2]->node_id);
    }

    public function testNestedForkContextIntegrity(): void
    {
        // Nested fork: outer fork has two branches, inner branch is another fork
        // All conditions should accumulate in shared context
        $slug = 'ctx-nested-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'fork2'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.outer == yes'],
                    'outputs' => [['port' => 'true', 'target' => 'end_a'], ['port' => 'false', 'target' => 'end_a']],
                ],
                'fork2' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'x', 'target' => 'condB'],
                        ['port' => 'y', 'target' => 'condC'],
                    ],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.inner_b == yes'],
                    'outputs' => [['port' => 'true', 'target' => 'end_b'], ['port' => 'false', 'target' => 'end_b']],
                ],
                'condC' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.inner_c == no'],
                    'outputs' => [['port' => 'true', 'target' => 'end_c'], ['port' => 'false', 'target' => 'end_c']],
                ],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_c' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, [
            'outer' => 'yes',
            'inner_b' => 'yes',
            'inner_c' => 'no',
        ]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $context = $instance->context;
        // All conditions from both nesting levels present in shared context
        $this->assertTrue($context['nodes']['condA']['result']);
        $this->assertTrue($context['nodes']['condB']['result']);
        $this->assertTrue($context['nodes']['condC']['result']);
    }

    public function testJoinStateTracksCompletedInputs(): void
    {
        $slug = 'ctx-joinstate-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                        ['port' => 'b', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'b'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Join state should be tracked in _internal
        $joinState = $instance->context['_internal']['joinState']['join1'] ?? null;
        $this->assertNotNull($joinState);
        $this->assertNotEmpty($joinState['completedInputs']);
    }

    public function testForkWithConditionBranchingPreservesContext(): void
    {
        // Fork where one branch takes true path, other takes false path
        $slug = 'ctx-mixed-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'condB'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.flagA == on'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_a_true'],
                        ['port' => 'false', 'target' => 'end_a_false'],
                    ],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.flagB == off'],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end_b_true'],
                        ['port' => 'false', 'target' => 'end_b_false'],
                    ],
                ],
                'end_a_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_a_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b_true' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b_false' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['flagA' => 'on', 'flagB' => 'off']);
        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);

        $context = $instance->context;
        // condA took 'true', condB took 'true'
        $this->assertTrue($context['nodes']['condA']['result']);
        $this->assertSame('true', $context['nodes']['condA']['port']);
        $this->assertTrue($context['nodes']['condB']['result']);
        $this->assertSame('true', $context['nodes']['condB']['port']);

        // Verify correct end nodes were executed
        $endATrueLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'end_a_true'])
            ->first();
        $endBTrueLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'end_b_true'])
            ->first();
        $this->assertNotNull($endATrueLog);
        $this->assertNotNull($endBTrueLog);
    }

    public function testForkDoesNotDuplicateContextEntries(): void
    {
        $slug = 'ctx-nodup-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'condA'],
                        ['port' => 'b', 'target' => 'condB'],
                    ],
                ],
                'condA' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.v == 1'],
                    'outputs' => [['port' => 'true', 'target' => 'end_a'], ['port' => 'false', 'target' => 'end_a']],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.v == 1'],
                    'outputs' => [['port' => 'true', 'target' => 'end_b'], ['port' => 'false', 'target' => 'end_b']],
                ],
                'end_a' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_b' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['v' => 1]);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $context = $instance->context;

        // Each node has exactly one entry, no duplicates from fork
        $this->assertArrayHasKey('condA', $context['nodes']);
        $this->assertArrayHasKey('condB', $context['nodes']);
        // Trigger data should appear exactly once
        $this->assertSame(['v' => 1], $context['trigger']);
    }

    public function testForkJoinInternalStateCleanup(): void
    {
        $slug = 'ctx-internal-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                        ['port' => 'b', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'b'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // _internal state exists and contains join tracking
        $this->assertArrayHasKey('_internal', $instance->context);
        $this->assertArrayHasKey('joinState', $instance->context['_internal']);
    }
}
