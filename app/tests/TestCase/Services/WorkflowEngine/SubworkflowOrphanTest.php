<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Tests for subworkflow parent/child lifecycle and orphan detection.
 *
 * Validates that child workflows resume parents on completion,
 * and documents current behavior when parents are cancelled or deleted.
 */
class SubworkflowOrphanTest extends BaseTestCase
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
    // Happy path: child completes → parent resumes
    // =====================================================

    public function testChildCompletionResumesParent(): void
    {
        // Child with a delay so parent reference is set before child completes.
        // After resuming the child, the parent should also complete.
        $childSlug = 'sub-child-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug, ['key' => 'val']);
        $this->assertTrue($result->isSuccess());

        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        // Parent is waiting for child to complete
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $parentInstance->status);

        // Get child instance
        $childInstanceId = $parentInstance->context['nodes']['sub1']['childInstanceId'];
        $this->assertNotNull($childInstanceId);

        // Resume child from delay → end → triggers parent resume
        $resumeResult = $this->engine->resumeWorkflow($childInstanceId, 'delay1', 'default');
        $this->assertTrue($resumeResult->isSuccess());

        // Parent should now be completed
        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $parentInstance->status);
    }

    public function testSynchronousChildCompletionResumesParent(): void
    {
        $childSlug = 'sub-child-sync-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-sync-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);

        $this->assertTrue($result->isSuccess());
        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $parentInstance->status);
        $this->assertNotNull($parentInstance->context['nodes']['sub1']['childInstanceId'] ?? null);
    }

    public function testForkedSynchronousChildResumesParentOnce(): void
    {
        $childSlug = 'sub-child-fork-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['target' => 'end1'],
                        ['target' => 'end2'],
                    ],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end2' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-fork-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);

        $this->assertTrue($result->isSuccess(), (string)$result->reason);
        $parent = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $parent->status);
        $this->assertEmpty($parent->active_nodes);
    }

    public function testParallelChildCompletionsBothResumeParent(): void
    {
        $childSlug = 'sub-child-parallel-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-parallel-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['target' => 'sub1'],
                        ['target' => 'sub2'],
                    ],
                ],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['target' => 'join1']],
                ],
                'sub2' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['target' => 'join1']],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($parentSlug);
        $this->assertTrue($startResult->isSuccess());
        $parentId = $startResult->data['instanceId'];
        $parent = $this->instancesTable->get($parentId);
        $firstChildId = $parent->context['nodes']['sub1']['childInstanceId'];
        $secondChildId = $parent->context['nodes']['sub2']['childInstanceId'];

        $firstResume = $this->engine->resumeWorkflow($firstChildId, 'delay1', 'default');
        $this->assertTrue($firstResume->isSuccess(), (string)$firstResume->reason);
        $parent = $this->instancesTable->get($parentId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $parent->status);

        $secondResume = $this->engine->resumeWorkflow($secondChildId, 'delay1', 'default');
        $this->assertTrue($secondResume->isSuccess(), (string)$secondResume->reason);
        $parent = $this->instancesTable->get($parentId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $parent->status);
    }

    public function testChildContextHasParentReference(): void
    {
        $childSlug = 'sub-child-ref-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-ref-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);
        $this->assertTrue($result->isSuccess());

        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        // Parent should be waiting for child (child has delay)
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $parentInstance->status);

        // Get child instance ID from parent context
        $childInstanceId = $parentInstance->context['nodes']['sub1']['childInstanceId'] ?? null;
        $this->assertNotNull($childInstanceId);

        // Child context should reference parent
        $childInstance = $this->instancesTable->get($childInstanceId);
        $this->assertSame($parentInstance->id, $childInstance->context['_internal']['parentInstanceId']);
        $this->assertSame('sub1', $childInstance->context['_internal']['parentNodeId']);
    }

    // =====================================================
    // Parent cancelled → child subworkflows NOT auto-cancelled
    // =====================================================

    public function testParentCancelledChildRemainsActive(): void
    {
        // Current engine behavior: cancelWorkflow does NOT cascade to children.
        $childSlug = 'sub-child-orphan-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '7d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-cancel-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);
        $parentId = $result->data['instanceId'];
        $parentInstance = $this->instancesTable->get($parentId);
        $childId = $parentInstance->context['nodes']['sub1']['childInstanceId'];

        // Cancel parent
        $cancelResult = $this->engine->cancelWorkflow($parentId, 'Parent cancelled');
        $this->assertTrue($cancelResult->isSuccess());

        $parentInstance = $this->instancesTable->get($parentId);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $parentInstance->status);

        // Child is NOT auto-cancelled — it becomes an orphan
        $childInstance = $this->instancesTable->get($childId);
        $this->assertNotSame(
            WorkflowInstance::STATUS_CANCELLED,
            $childInstance->status,
            'Current behavior: child is not auto-cancelled when parent is cancelled',
        );

        $resumeResult = $this->engine->resumeWorkflow($childId, 'delay1', 'default');
        $this->assertTrue($resumeResult->isSuccess(), (string)$resumeResult->reason);
        $childInstance = $this->instancesTable->get($childId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $childInstance->status);
        $parentInstance = $this->instancesTable->get($parentId);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $parentInstance->status);
    }

    // =====================================================
    // Child fails → parent behavior
    // =====================================================

    public function testChildWithInvalidSlugFailsGracefully(): void
    {
        $parentSlug = 'sub-parent-badc-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => 'nonexistent-child-' . uniqid()],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('failed to start', $result->reason);
    }

    public function testSubworkflowNodeWithoutSlugFails(): void
    {
        $parentSlug = 'sub-no-slug-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Missing workflowSlug throws RuntimeException, transaction rolls back
        $result = $this->engine->startWorkflow($parentSlug);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('no workflowSlug', $result->reason);
    }

    // =====================================================
    // Parent deleted → orphan child detection
    // =====================================================

    public function testChildReferencingDeletedParentHandledGracefully(): void
    {
        // Simulate orphan: create child with _internal parent reference to non-existent ID.
        $childSlug = 'sub-orphan-child-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        // Start the child workflow normally first
        $this->engine->startWorkflow($childSlug);
        // If it auto-completes (trigger → end), it will try to resume a non-existent parent.
        // We need to set up the parent reference BEFORE it completes, so use a delay.

        // Re-create with delay to prevent immediate completion
        $childSlug2 = 'sub-orphan-child2-' . uniqid();
        $this->createWorkflow($childSlug2, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1h'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $childResult2 = $this->engine->startWorkflow($childSlug2);
        $this->assertTrue($childResult2->isSuccess());
        $childId = $childResult2->data['instanceId'];

        // Manually inject orphan parent reference
        $childInstance = $this->instancesTable->get($childId);
        $context = $childInstance->context;
        $context['_internal']['parentInstanceId'] = 999999;
        $context['_internal']['parentNodeId'] = 'sub1';
        $childInstance->context = $context;
        $this->instancesTable->saveOrFail($childInstance);

        // Resume child from delay → will reach end → try to resume non-existent parent
        // This should fail gracefully (resumeWorkflow will return failure for missing instance)
        $resumeResult = $this->engine->resumeWorkflow($childId, 'delay1', 'default');
        $this->assertTrue($resumeResult->isSuccess(), (string)$resumeResult->reason);

        // Child itself should complete
        $childInstance = $this->instancesTable->get($childId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $childInstance->status);
    }

    public function testParentStoresChildInstanceIdInContext(): void
    {
        $childSlug = 'sub-child-id-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-id-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($parentSlug);
        $this->assertTrue($result->isSuccess());

        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        $nodeData = $parentInstance->context['nodes']['sub1'] ?? null;
        $this->assertNotNull($nodeData);
        $this->assertArrayHasKey('childInstanceId', $nodeData);
        $this->assertArrayHasKey('result', $nodeData);

        // Verify child actually exists
        $childInstance = $this->instancesTable->get($nodeData['childInstanceId']);
        $this->assertNotNull($childInstance);
    }

    public function testChildPassesTriggerDataFromParent(): void
    {
        $childSlug = 'sub-child-data-' . uniqid();
        $this->createWorkflow($childSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'delay1']]],
                'delay1' => ['type' => 'delay', 'config' => ['duration' => '1d'], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $parentSlug = 'sub-parent-data-' . uniqid();
        $this->createWorkflow($parentSlug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'sub1']]],
                'sub1' => [
                    'type' => 'subworkflow',
                    'config' => ['workflowSlug' => $childSlug],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $triggerData = ['entity_id' => 42, 'action' => 'create'];
        $result = $this->engine->startWorkflow($parentSlug, $triggerData);
        $this->assertTrue($result->isSuccess());

        $parentInstance = $this->instancesTable->get($result->data['instanceId']);
        $childId = $parentInstance->context['nodes']['sub1']['childInstanceId'];
        $childInstance = $this->instancesTable->get($childId);

        // Child receives parent's trigger data
        $this->assertSame($triggerData, $childInstance->context['trigger']);
    }
}
