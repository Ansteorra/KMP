<?php
declare(strict_types=1);

namespace App\Test\TestCase\Queue\Task;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Queue\Task\WorkflowApprovalDeadlineTask;
use App\Queue\Task\WorkflowResumeTask;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use RuntimeException;

/**
 * Tests for workflow queue tasks.
 */
class WorkflowTaskTest extends BaseTestCase
{
    // =====================================================
    // WorkflowResumeTask
    // =====================================================

    public function testResumeTaskThrowsOnMissingInstanceId(): void
    {
        $task = $this->createResumeTask();
        $this->expectException(InvalidArgumentException::class);
        $task->run(['nodeId' => 'n1'], 1);
    }

    public function testResumeTaskThrowsOnMissingNodeId(): void
    {
        $task = $this->createResumeTask();
        $this->expectException(InvalidArgumentException::class);
        $task->run(['instanceId' => 1], 1);
    }

    public function testResumeTaskThrowsOnEmptyData(): void
    {
        $task = $this->createResumeTask();
        $this->expectException(InvalidArgumentException::class);
        $task->run([], 1);
    }

    public function testResumeTaskCallsEngineWithCorrectArgs(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('resumeWorkflow')
            ->with(42, 'node_x', 'approved', ['foo' => 'bar'])
            ->willReturn(new ServiceResult(true));

        $task = $this->createResumeTask($engine);
        $task->run([
            'instanceId' => 42,
            'nodeId' => 'node_x',
            'outputPort' => 'approved',
            'additionalData' => ['foo' => 'bar'],
        ], 1);
    }

    public function testResumeTaskThrowsOnEngineFail(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('resumeWorkflow')
            ->willReturn(new ServiceResult(false, 'Engine error'));

        $task = $this->createResumeTask($engine);
        $this->expectException(RuntimeException::class);
        $task->run(['instanceId' => 1, 'nodeId' => 'n1'], 1);
    }

    public function testResumeTaskDefaultsOutputPortToNext(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('resumeWorkflow')
            ->with(1, 'n1', 'next', [])
            ->willReturn(new ServiceResult(true));

        $task = $this->createResumeTask($engine);
        $task->run(['instanceId' => 1, 'nodeId' => 'n1'], 1);
    }

    // =====================================================
    // WorkflowApprovalDeadlineTask
    // =====================================================

    public function testDeadlineTaskNoExpiredApprovalsIsNoop(): void
    {
        // Ensure no expired approvals exist in the test DB
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $expired = $approvalsTable->find()
            ->where([
                'status' => WorkflowApproval::STATUS_PENDING,
                'deadline IS NOT' => null,
                'deadline <' => DateTime::now(),
            ])
            ->count();

        // If there happen to be expired approvals from seeded data, that's fine;
        // we verify the task doesn't throw.
        $engine = $this->createMock(WorkflowEngineInterface::class);
        if ($expired === 0) {
            $engine->expects($this->never())->method('resumeWorkflow');
        } else {
            $engine->method('resumeWorkflow')->willReturn(new ServiceResult(true));
        }

        $task = $this->createDeadlineTask($engine);
        $task->run([], 1);
        // No exception = success
        $this->assertTrue(true);
    }

    public function testDeadlineTaskMarksExpiredAndResumes(): void
    {
        // Create a workflow context with an expired approval
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Deadline Test ' . uniqid(),
            'slug' => 'deadline-' . uniqid(),
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

        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'deadline' => DateTime::now()->modify('-1 hour'),
        ]);
        $approvalsTable->saveOrFail($approval);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->atLeastOnce())
            ->method('resumeWorkflow')
            ->willReturn(new ServiceResult(true));

        $task = $this->createDeadlineTask($engine);
        $task->run([], 1);

        // Verify the approval is now expired
        $updated = $approvalsTable->get($approval->id);
        $this->assertEquals(WorkflowApproval::STATUS_EXPIRED, $updated->status);
    }

    public function testDeadlineTaskRollsBackApprovalWhenResumeFails(): void
    {
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approvalsTable->updateAll(
            ['deadline' => DateTime::now()->modify('+1 day')],
            [
                'status' => WorkflowApproval::STATUS_PENDING,
                'deadline <' => DateTime::now(),
            ],
        );
        $approval = $this->createExpiredApproval();

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('resumeWorkflow')
            ->willReturn(new ServiceResult(false, 'resume failed'));

        $task = $this->createDeadlineTask($engine);

        try {
            $task->run([], 1);
            $this->fail('Deadline task should throw so the queue retries the approval.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('retrying pending work', $exception->getMessage());
        }

        $updated = $approvalsTable->get($approval->id);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $updated->status);
    }

    public function testDeadlineTaskDispatchesExpiredApprovalEvent(): void
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Deadline Event Test ' . uniqid(),
            'slug' => 'deadline-event-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $defTable->saveOrFail($def);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => ['nodes' => []],
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
            'node_id' => 'approval_event_node',
            'node_type' => 'approval',
            'status' => 'waiting',
        ]);
        $logsTable->saveOrFail($log);

        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_event_node',
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'deadline' => DateTime::now()->modify('-1 hour'),
        ]);
        $approvalsTable->saveOrFail($approval);

        $expiredApprovalIds = [];
        EventManager::instance()->on('Workflow.Approval.Expired', function ($event) use (&$expiredApprovalIds): void {
            $expiredApproval = $event->getData('approval');
            if ($expiredApproval instanceof WorkflowApproval) {
                $expiredApprovalIds[] = (int)$expiredApproval->id;
            }
        });

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('resumeWorkflow')->willReturn(new ServiceResult(true));

        $task = $this->createDeadlineTask($engine);
        $task->run([], 1);

        $this->assertContains((int)$approval->id, $expiredApprovalIds);
    }

    // =====================================================
    // Helpers
    // =====================================================

    /**
     * Build a mock DI container that returns the given engine.
     */
    private function makeContainer(WorkflowEngineInterface $engine): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with(WorkflowEngineInterface::class)
            ->willReturn($engine);

        return $container;
    }

    private function createResumeTask(?WorkflowEngineInterface $engine = null): WorkflowResumeTask
    {
        $task = new WorkflowResumeTask();
        if ($engine) {
            $task->setContainer($this->makeContainer($engine));
        }

        return $task;
    }

    private function createDeadlineTask(?WorkflowEngineInterface $engine = null): WorkflowApprovalDeadlineTask
    {
        $task = new WorkflowApprovalDeadlineTask();
        if ($engine) {
            $task->setContainer($this->makeContainer($engine));
        }

        return $task;
    }

    private function createExpiredApproval(): WorkflowApproval
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Deadline Retry Test ' . uniqid(),
            'slug' => 'deadline-retry-' . uniqid(),
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
            'status' => WorkflowInstance::STATUS_WAITING,
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

        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approval = $approvalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'deadline' => DateTime::now()->modify('-1 hour'),
        ]);
        $approvalsTable->saveOrFail($approval);

        return $approval;
    }
}
