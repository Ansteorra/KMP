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
 * Tests for the forEach node type in the workflow engine.
 */
class ForEachNodeTest extends BaseTestCase
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
    // Basic iteration
    // =====================================================

    public function testForEachIteratesOverArray(): void
    {
        $slug = 'foreach-basic-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b', 'c']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Should have aggregated results in context
        $context = $instance->context;
        $this->assertArrayHasKey('forEach', $context);
        $this->assertArrayHasKey('forEach1', $context['forEach']);
        $this->assertSame(3, $context['forEach']['forEach1']['processed']);
        $this->assertEmpty($context['forEach']['forEach1']['errors']);
    }

    public function testForEachSetsItemAndIndexVariables(): void
    {
        $slug = 'foreach-vars-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'myItem',
                        'indexVariable' => 'myIdx',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['x', 'y']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);

        // After completion, iteration variables should be cleaned up
        $context = $instance->context;
        $this->assertArrayNotHasKey('myItem', $context);
        $this->assertArrayNotHasKey('myIdx', $context);
    }

    // =====================================================
    // Empty collection
    // =====================================================

    public function testForEachEmptyCollectionGoesToComplete(): void
    {
        $slug = 'foreach-empty-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => []]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $context = $instance->context;
        $this->assertSame(0, $context['forEach']['forEach1']['processed']);
        $this->assertEmpty($context['forEach']['forEach1']['errors']);
    }

    public function testForEachMissingCollectionTreatedAsEmpty(): void
    {
        $slug = 'foreach-missing-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.nonexistent',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, []);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame(0, $instance->context['forEach']['forEach1']['processed']);
    }

    // =====================================================
    // Error handling: continueOnError
    // =====================================================

    public function testForEachContinueOnErrorSkipsFailures(): void
    {
        $slug = 'foreach-continue-err-' . uniqid();
        // Use an action node that will fail (unknown action) in the iterate path
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                        'continueOnError' => true,
                    ],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'failAction'],
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'failAction' => [
                    'type' => 'action',
                    'config' => ['action' => 'NonExistent.action'],
                    'outputs' => [],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b', 'c']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $forEachData = $instance->context['forEach']['forEach1'];
        // All 3 items attempted, all errored but processing continued
        $this->assertSame(3, $forEachData['processed']);
        $this->assertCount(3, $forEachData['errors']);
    }

    public function testForEachStopsOnFirstErrorWhenNotContinueOnError(): void
    {
        $slug = 'foreach-stop-err-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                        'continueOnError' => false,
                    ],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'failAction'],
                        ['port' => 'complete', 'target' => 'end1'],
                        ['port' => 'error', 'target' => 'errorEnd'],
                    ],
                ],
                'failAction' => [
                    'type' => 'action',
                    'config' => ['action' => 'NonExistent.action'],
                    'outputs' => [],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'errorEnd' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b', 'c']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        // Should complete via error port -> errorEnd
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $forEachData = $instance->context['forEach']['forEach1'];
        // Stopped at first item
        $this->assertSame(0, $forEachData['processed']);
        $this->assertCount(1, $forEachData['errors']);
        $this->assertSame(0, $forEachData['errors'][0]['index']);
    }

    // =====================================================
    // Aggregated results
    // =====================================================

    public function testForEachStoresAggregatedResults(): void
    {
        $slug = 'foreach-results-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => [10, 20, 30]]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);

        $forEachData = $instance->context['forEach']['forEach1'];
        $this->assertArrayHasKey('processed', $forEachData);
        $this->assertArrayHasKey('errors', $forEachData);
        $this->assertArrayHasKey('results', $forEachData);
        $this->assertSame(3, $forEachData['processed']);
    }

    // =====================================================
    // Iteration with child node execution
    // =====================================================

    public function testForEachExecutesIterateChildNodes(): void
    {
        $slug = 'foreach-iterate-child-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'conditionInLoop'],
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'conditionInLoop' => [
                    'type' => 'condition',
                    'config' => [
                        'expression' => 'currentItem == yes',
                    ],
                    'outputs' => [
                        ['port' => 'true', 'target' => 'end1'],
                        ['port' => 'false', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['yes', 'no', 'yes']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Condition node should have been executed 3 times (once per item)
        $condLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'conditionInLoop'])
            ->all()
            ->toArray();
        $this->assertCount(3, $condLogs);
    }

    // =====================================================
    // Default variable names
    // =====================================================

    public function testForEachUsesDefaultVariableNames(): void
    {
        $slug = 'foreach-defaults-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        // No itemVariable/indexVariable — should default
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Default variable names (currentItem, currentIndex) should be cleaned up
        $context = $instance->context;
        $this->assertArrayNotHasKey('currentItem', $context);
        $this->assertArrayNotHasKey('currentIndex', $context);
    }

    // =====================================================
    // Log entries
    // =====================================================

    public function testForEachCreatesExecutionLog(): void
    {
        $slug = 'foreach-log-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b']]);

        $this->assertTrue($result->isSuccess());

        $forEachLogs = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $result->data['instanceId'],
                'node_id' => 'forEach1',
            ])
            ->all()
            ->toArray();

        $this->assertCount(1, $forEachLogs);
        $this->assertSame(WorkflowExecutionLog::STATUS_COMPLETED, $forEachLogs[0]->status);
        $this->assertSame(2, $forEachLogs[0]->output_data['processed']);
    }

    public function testForEachRejectsWaitingDescendantsAtRuntime(): void
    {
        $slug = 'foreach-waiting-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => ['collection' => '$.trigger.items'],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'delay1'],
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'delay1' => ['type' => 'delay', 'config' => [], 'outputs' => []],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b']]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('cannot execute waiting node', $result->reason);
    }

    public function testForEachRejectsExplicitAsyncActionAtRuntime(): void
    {
        $slug = 'foreach-async-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => ['collection' => '$.trigger.items'],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'action1'],
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'action1' => [
                    'type' => 'action',
                    'config' => ['action' => 'Unknown.AsyncAction', 'isAsync' => true],
                    'outputs' => [],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a', 'b']]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('cannot execute waiting node', $result->reason);
    }

    // =====================================================
    // Nested object iteration
    // =====================================================

    public function testForEachWithNestedObjectCollection(): void
    {
        $slug = 'foreach-nested-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.roster.warrants',
                        'itemVariable' => 'warrant',
                        'indexVariable' => 'idx',
                    ],
                    'outputs' => [
                        ['port' => 'complete', 'target' => 'end1'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $warrants = [
            ['id' => 1, 'name' => 'Warrant A'],
            ['id' => 2, 'name' => 'Warrant B'],
        ];
        $result = $this->engine->startWorkflow($slug, ['roster' => ['warrants' => $warrants]]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame(2, $instance->context['forEach']['forEach1']['processed']);
    }

    // =====================================================
    // Error port fires on failure
    // =====================================================

    public function testForEachErrorPortFiresOnFailure(): void
    {
        $slug = 'foreach-error-port-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                        'continueOnError' => false,
                    ],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'failAction'],
                        ['port' => 'complete', 'target' => 'successEnd'],
                        ['port' => 'error', 'target' => 'errorEnd'],
                    ],
                ],
                'failAction' => [
                    'type' => 'action',
                    'config' => ['action' => 'NonExistent.action'],
                    'outputs' => [],
                ],
                'successEnd' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'errorEnd' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['fail']]);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Error end node should have been reached, not success end
        $errorEndLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'errorEnd'])
            ->all()
            ->toArray();
        $this->assertCount(1, $errorEndLogs);

        $successEndLogs = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'successEnd'])
            ->all()
            ->toArray();
        $this->assertCount(0, $successEndLogs);
    }

    // =====================================================
    // forEach log shows failed status when stopping on error
    // =====================================================

    public function testForEachLogShowsFailedStatusOnError(): void
    {
        $slug = 'foreach-fail-log-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'forEach1']]],
                'forEach1' => [
                    'type' => 'forEach',
                    'config' => [
                        'collection' => '$.trigger.items',
                        'itemVariable' => 'currentItem',
                        'indexVariable' => 'currentIndex',
                        'continueOnError' => false,
                    ],
                    'outputs' => [
                        ['port' => 'iterate', 'target' => 'failAction'],
                        ['port' => 'error', 'target' => 'errorEnd'],
                    ],
                ],
                'failAction' => [
                    'type' => 'action',
                    'config' => ['action' => 'NonExistent.action'],
                    'outputs' => [],
                ],
                'errorEnd' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['items' => ['a']]);

        $this->assertTrue($result->isSuccess());

        $forEachLogs = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $result->data['instanceId'],
                'node_id' => 'forEach1',
            ])
            ->all()
            ->toArray();

        $this->assertCount(1, $forEachLogs);
        $this->assertSame(WorkflowExecutionLog::STATUS_FAILED, $forEachLogs[0]->status);
        $this->assertNotEmpty($forEachLogs[0]->error_message);
    }
}
