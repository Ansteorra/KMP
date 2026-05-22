<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\ORM\TableRegistry;

/**
 * Tests for fork/join deadlock detection and edge cases.
 *
 * Validates behavior when branches don't all reach the join node,
 * when approvals block branches, and when join expectations mismatch.
 */
class ForkJoinDeadlockTest extends BaseTestCase
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
    // Happy path: all branches reach join
    // =====================================================

    public function testForkAllBranchesReachJoinCompletes(): void
    {
        $slug = 'fjd-happy-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                        ['port' => 'b', 'target' => 'join1'],
                        ['port' => 'c', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'b'],
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'c'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);

        $this->assertTrue($result->isSuccess());
        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertEmpty($instance->active_nodes);
    }

    // =====================================================
    // Branch that ends without reaching join
    // =====================================================

    public function testForkWithBranchEndingBeforeJoin(): void
    {
        // Branch A goes to join, branch B goes to end (never reaches join).
        // The join only has one edge source, so it should complete with just A.
        $slug = 'fjd-early-end-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                        ['port' => 'b', 'target' => 'end_early'],
                    ],
                ],
                'end_early' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Both end nodes should have been reached
        $earlyEndLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'end_early'])
            ->first();
        $finalEndLog = $this->logsTable->find()
            ->where(['workflow_instance_id' => $instance->id, 'node_id' => 'end1'])
            ->first();
        $this->assertNotNull($earlyEndLog);
        $this->assertNotNull($finalEndLog);
    }

    public function testForkWithIntermediateNodesToJoinCompletes(): void
    {
        // Intermediate condition nodes between fork and join should still
        // contribute distinct completed inputs to the join.
        $slug = 'fjd-intermediate-' . uniqid();
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
                    'outputs' => [['port' => 'true', 'target' => 'join1'], ['port' => 'false', 'target' => 'join1']],
                ],
                'condB' => [
                    'type' => 'condition',
                    'config' => ['expression' => 'trigger.x == 1'],
                    'outputs' => [['port' => 'true', 'target' => 'join1'], ['port' => 'false', 'target' => 'join1']],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'condA', 'target' => 'join1'],
                ['source' => 'condB', 'target' => 'join1'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, ['x' => 1]);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        // Verify both conditions were still executed
        $this->assertArrayHasKey('condA', $instance->context['nodes']);
        $this->assertArrayHasKey('condB', $instance->context['nodes']);
    }

    // =====================================================
    // Approval blocks a branch — join waits
    // =====================================================

    public function testForkWithApprovalBranchInstanceWaits(): void
    {
        // Branch A goes to end, branch B has an approval (waiting).
        // Instance should be waiting because approval blocks branch B.
        $slug = 'fjd-approval-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'end_fast'],
                        ['port' => 'b', 'target' => 'approval1'],
                    ],
                ],
                'approval1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'permission' => 'can_approve',
                        'requiredCount' => 1,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_approved'],
                        ['port' => 'rejected', 'target' => 'end_rejected'],
                    ],
                ],
                'end_fast' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_rejected' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        // Instance should be waiting because approval blocks branch B
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Approval log should be in waiting state
        $approvalLog = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $instance->id,
                'node_id' => 'approval1',
                'status' => WorkflowExecutionLog::STATUS_WAITING,
            ])
            ->first();
        $this->assertNotNull($approvalLog);
    }

    public function testForkWithApprovalResumesAndCompletes(): void
    {
        // Fork: branch A → end, branch B → approval → end
        // After approval resume, instance should complete.
        $slug = 'fjd-approve-resume-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'end_fast'],
                        ['port' => 'b', 'target' => 'approval1'],
                    ],
                ],
                'approval1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'permission' => 'can_approve',
                        'requiredCount' => 1,
                    ],
                    'outputs' => [
                        ['port' => 'approved', 'target' => 'end_approved'],
                    ],
                ],
                'end_fast' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $this->assertTrue($startResult->isSuccess());
        $instanceId = $startResult->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Resume after approval
        $resumeResult = $this->engine->resumeWorkflow($instanceId, 'approval1', 'approved');
        $this->assertTrue($resumeResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    // =====================================================
    // Join with no edges defined — immediate advance
    // =====================================================

    public function testJoinWithSingleSourceAdvancesImmediately(): void
    {
        // Join with only one input source completes immediately when that source arrives.
        $slug = 'fjd-single-src-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                    ],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    // =====================================================
    // Cancellation of fork with waiting join
    // =====================================================

    public function testCancelForkWithWaitingApproval(): void
    {
        $slug = 'fjd-cancel-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'end_fast'],
                        ['port' => 'b', 'target' => 'approval1'],
                    ],
                ],
                'approval1' => [
                    'type' => 'approval',
                    'config' => [
                        'approverType' => 'permission',
                        'permission' => 'can_approve',
                        'requiredCount' => 1,
                    ],
                    'outputs' => [['port' => 'approved', 'target' => 'end_approved']],
                ],
                'end_fast' => ['type' => 'end', 'config' => [], 'outputs' => []],
                'end_approved' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
        ]);

        $startResult = $this->engine->startWorkflow($slug);
        $instanceId = $startResult->data['instanceId'];

        // Cancel while waiting on approval
        $cancelResult = $this->engine->cancelWorkflow($instanceId, 'Deadlock timeout');
        $this->assertTrue($cancelResult->isSuccess());

        $instance = $this->instancesTable->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $instance->status);
        $this->assertSame('Deadlock timeout', $instance->error_info['cancellation_reason']);

        // Pending approvals should be cancelled
        $pendingApprovals = $this->approvalsTable->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->count();
        $this->assertSame(0, $pendingApprovals);
    }

    public function testForkWithDelayBranchJoinWaits(): void
    {
        // Branch A goes to join, branch B has a delay (waiting).
        // Join should wait since B hasn't arrived.
        $slug = 'fjd-delay-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'fork1']]],
                'fork1' => [
                    'type' => 'fork',
                    'config' => [],
                    'outputs' => [
                        ['port' => 'a', 'target' => 'join1'],
                        ['port' => 'b', 'target' => 'delay1'],
                    ],
                ],
                'delay1' => [
                    'type' => 'delay',
                    'config' => ['duration' => '7d'],
                    'outputs' => [['port' => 'default', 'target' => 'join1']],
                ],
                'join1' => ['type' => 'join', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
            ],
            'edges' => [
                ['source' => 'fork1', 'target' => 'join1', 'sourcePort' => 'a'],
                ['source' => 'delay1', 'target' => 'join1'],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        // Instance should be waiting because delay blocks branch B
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        // Join should have a waiting log
        $joinLog = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $instance->id,
                'node_id' => 'join1',
                'status' => WorkflowExecutionLog::STATUS_WAITING,
            ])
            ->first();
        $this->assertNotNull($joinLog);
    }
}
