<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowTask;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\ContainerInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for the humanTask node type in DefaultWorkflowEngine.
 */
class HumanTaskNodeTest extends BaseTestCase
{
    private DefaultWorkflowEngine $engine;
    private $defTable;
    private $versionsTable;
    private $instancesTable;
    private $logsTable;
    private $tasksTable;

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
        $this->tasksTable = TableRegistry::getTableLocator()->get('WorkflowTasks');
    }

    /**
     * Create a workflow definition with a published version.
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
     * Build a simple workflow: trigger -> humanTask -> end
     */
    private function buildHumanTaskWorkflow(array $taskConfig): array
    {
        return [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'task1']],
                ],
                'task1' => [
                    'type' => 'humanTask',
                    'config' => $taskConfig,
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => [
                    'type' => 'end',
                    'config' => [],
                    'outputs' => [],
                ],
            ],
        ];
    }

    // ======================================================================
    // Test: Task creation on humanTask node execution
    // ======================================================================

    public function testHumanTaskNodeCreatesTaskRecord(): void
    {
        $slug = 'ht-create-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Review document',
            'assignTo' => '$.trigger.member_id',
            'formFields' => [
                ['name' => 'approved', 'type' => 'checkbox', 'label' => 'Approve', 'required' => true],
            ],
        ]));

        $result = $this->engine->startWorkflow($slug, ['member_id' => self::ADMIN_MEMBER_ID]);
        $this->assertTrue($result->isSuccess());

        $instanceId = $result->data['instanceId'];
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        $this->assertNotNull($task, 'A workflow task record should be created');
        $this->assertEquals('task1', $task->node_id);
        $this->assertEquals('Review document', $task->task_title);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $task->assigned_to);
        $this->assertEquals(WorkflowTask::STATUS_PENDING, $task->status);
        $this->assertNull($task->form_data);
        $this->assertNotEmpty($task->form_definition);
    }

    // ======================================================================
    // Test: Workflow pauses (WAITING status)
    // ======================================================================

    public function testHumanTaskNodePausesWorkflow(): void
    {
        $slug = 'ht-pause-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Approval form',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $this->assertTrue($result->isSuccess());

        $instance = $this->instancesTable->get($result->data['instanceId']);
        $this->assertEquals(WorkflowInstance::STATUS_WAITING, $instance->status);
    }

    public function testHumanTaskNodeSetsExecutionLogToWaiting(): void
    {
        $slug = 'ht-log-wait-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Test log',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $instanceId = $result->data['instanceId'];

        $log = $this->logsTable->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'node_id' => 'task1',
            ])
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(WorkflowExecutionLog::STATUS_WAITING, $log->status);
    }

    // ======================================================================
    // Test: Task completion resumes workflow
    // ======================================================================

    public function testCompleteHumanTaskResumesWorkflow(): void
    {
        $slug = 'ht-complete-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Complete me',
            'formFields' => [
                ['name' => 'comment', 'type' => 'textarea', 'label' => 'Comment', 'required' => false],
            ],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $instanceId = $result->data['instanceId'];

        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        $completeResult = $this->engine->completeHumanTask(
            $task->id,
            ['comment' => 'Looks good'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($completeResult->isSuccess());

        // Workflow should be completed (trigger -> task -> end)
        $instance = $this->instancesTable->get($instanceId);
        $this->assertEquals(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    public function testCompleteHumanTaskSavesFormData(): void
    {
        $slug = 'ht-formdata-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Fill data',
            'formFields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false],
            ],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->engine->completeHumanTask(
            $task->id,
            ['name' => 'John Doe'],
            self::ADMIN_MEMBER_ID,
        );

        $updatedTask = $this->tasksTable->get($task->id);
        $this->assertEquals(WorkflowTask::STATUS_COMPLETED, $updatedTask->status);
        $this->assertEquals(['name' => 'John Doe'], $updatedTask->form_data);
        $this->assertNotNull($updatedTask->completed_at);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $updatedTask->completed_by);
    }

    // ======================================================================
    // Test: Form data merges into context via contextMapping
    // ======================================================================

    public function testContextMappingMergesFormDataIntoContext(): void
    {
        $slug = 'ht-ctx-map-' . uniqid();
        $this->createWorkflow($slug, [
            'nodes' => [
                'trigger1' => [
                    'type' => 'trigger',
                    'config' => [],
                    'outputs' => [['port' => 'default', 'target' => 'task1']],
                ],
                'task1' => [
                    'type' => 'humanTask',
                    'config' => [
                        'taskTitle' => 'Select approver',
                        'formFields' => [
                            ['name' => 'next_approver_id', 'type' => 'member_select', 'label' => 'Next Approver', 'required' => true],
                            ['name' => 'denial_reason', 'type' => 'textarea', 'label' => 'Reason', 'required' => false],
                        ],
                        'contextMapping' => [
                            'next_approver_id' => '$.task.next_approver_id',
                            'denial_reason' => '$.task.denial_reason',
                        ],
                    ],
                    'outputs' => [['port' => 'default', 'target' => 'end1']],
                ],
                'end1' => [
                    'type' => 'end',
                    'config' => [],
                    'outputs' => [],
                ],
            ],
        ]);

        $result = $this->engine->startWorkflow($slug, []);
        $instanceId = $result->data['instanceId'];

        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->first();

        $this->engine->completeHumanTask(
            $task->id,
            ['next_approver_id' => 42, 'denial_reason' => 'Not qualified'],
            self::ADMIN_MEMBER_ID,
        );

        $instance = $this->instancesTable->get($instanceId);
        $context = $instance->context;

        // The contextMapping maps to $.task.*, so check that path
        $this->assertEquals(42, $context['task']['next_approver_id'] ?? null);
        $this->assertEquals('Not qualified', $context['task']['denial_reason'] ?? null);
    }

    // ======================================================================
    // Test: assignTo resolution from context path
    // ======================================================================

    public function testAssignToResolvesFromContextPath(): void
    {
        $slug = 'ht-assign-ctx-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Assigned from context',
            'assignTo' => '$.trigger.reviewer_id',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, ['reviewer_id' => 99]);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->assertEquals(99, $task->assigned_to);
    }

    public function testAssignToWithLiteralMemberId(): void
    {
        $slug = 'ht-assign-lit-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Literal assign',
            'assignTo' => self::ADMIN_MEMBER_ID,
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->assertEquals(self::ADMIN_MEMBER_ID, $task->assigned_to);
    }

    // ======================================================================
    // Test: assignByRole resolution
    // ======================================================================

    public function testAssignByRoleIsStored(): void
    {
        $slug = 'ht-role-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Role-based task',
            'assignByRole' => 'can_manage_officers',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->assertEquals('can_manage_officers', $task->assigned_by_role);
        $this->assertNull($task->assigned_to);
    }

    // ======================================================================
    // Test: Task cancellation
    // ======================================================================

    public function testCancelHumanTask(): void
    {
        $slug = 'ht-cancel-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Cancel me',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $cancelResult = $this->engine->cancelHumanTask($task->id);
        $this->assertTrue($cancelResult->isSuccess());

        $updatedTask = $this->tasksTable->get($task->id);
        $this->assertEquals(WorkflowTask::STATUS_CANCELLED, $updatedTask->status);
    }

    public function testCannotCancelAlreadyCancelledTask(): void
    {
        $slug = 'ht-cancel-twice-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Double cancel',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->engine->cancelHumanTask($task->id);
        $cancelResult = $this->engine->cancelHumanTask($task->id);
        $this->assertFalse($cancelResult->isSuccess());
    }

    // ======================================================================
    // Test: Expired task handling
    // ======================================================================

    public function testExpiredTaskCannotBeCompleted(): void
    {
        $slug = 'ht-expired-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Expired task',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        // Manually set due_date in the past
        $task->due_date = DateTime::now()->modify('-1 day');
        $this->tasksTable->save($task);

        $completeResult = $this->engine->completeHumanTask($task->id, [], self::ADMIN_MEMBER_ID);
        $this->assertFalse($completeResult->isSuccess());
        $this->assertStringContainsString('expired', $completeResult->reason);

        $updatedTask = $this->tasksTable->get($task->id);
        $this->assertEquals(WorkflowTask::STATUS_EXPIRED, $updatedTask->status);
    }

    // ======================================================================
    // Test: Invalid task ID
    // ======================================================================

    public function testCompleteNonExistentTaskFails(): void
    {
        $result = $this->engine->completeHumanTask(999999, [], self::ADMIN_MEMBER_ID);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->reason);
    }

    public function testCancelNonExistentTaskFails(): void
    {
        $result = $this->engine->cancelHumanTask(999999);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->reason);
    }

    // ======================================================================
    // Test: Completing already-completed task
    // ======================================================================

    public function testCannotCompleteAlreadyCompletedTask(): void
    {
        $slug = 'ht-double-complete-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Complete twice',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->engine->completeHumanTask($task->id, [], self::ADMIN_MEMBER_ID);

        $secondResult = $this->engine->completeHumanTask($task->id, [], self::ADMIN_MEMBER_ID);
        $this->assertFalse($secondResult->isSuccess());
        $this->assertStringContainsString('no longer pending', $secondResult->reason);
    }

    // ======================================================================
    // Test: Missing required form fields
    // ======================================================================

    public function testMissingRequiredFieldsRejectsCompletion(): void
    {
        $slug = 'ht-required-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Required fields',
            'formFields' => [
                ['name' => 'approver_id', 'type' => 'member_select', 'label' => 'Approver', 'required' => true],
                ['name' => 'comment', 'type' => 'textarea', 'label' => 'Comment', 'required' => false],
            ],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        // Missing the required 'approver_id' field
        $completeResult = $this->engine->completeHumanTask(
            $task->id,
            ['comment' => 'optional only'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($completeResult->isSuccess());
        $this->assertStringContainsString('approver_id', $completeResult->reason);

        // Task should still be pending
        $updatedTask = $this->tasksTable->get($task->id);
        $this->assertEquals(WorkflowTask::STATUS_PENDING, $updatedTask->status);
    }

    public function testEmptyStringFailsRequiredValidation(): void
    {
        $slug = 'ht-empty-req-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Empty required',
            'formFields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
            ],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $completeResult = $this->engine->completeHumanTask(
            $task->id,
            ['name' => ''],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($completeResult->isSuccess());
        $this->assertStringContainsString('name', $completeResult->reason);
    }

    // ======================================================================
    // Test: Due date parsing
    // ======================================================================

    public function testDueDateParsedFromDurationString(): void
    {
        $slug = 'ht-duedate-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Due date test',
            'dueDate' => '7d',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->assertNotNull($task->due_date);
        // Should be roughly 7 days from now
        $diffDays = DateTime::now()->diffInDays($task->due_date);
        $this->assertGreaterThanOrEqual(6, $diffDays);
        $this->assertLessThanOrEqual(7, $diffDays);
    }

    public function testDueDateParsedFromExpressionSyntax(): void
    {
        $slug = 'ht-duedate-expr-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Expression due date',
            'dueDate' => '=$.now + 3 days',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $task = $this->tasksTable->find()
            ->where(['workflow_instance_id' => $result->data['instanceId']])
            ->first();

        $this->assertNotNull($task->due_date);
        $diffDays = DateTime::now()->diffInDays($task->due_date);
        $this->assertGreaterThanOrEqual(2, $diffDays);
        $this->assertLessThanOrEqual(3, $diffDays);
    }

    // ======================================================================
    // Test: Task stores taskId in workflow context
    // ======================================================================

    public function testTaskIdStoredInWorkflowContext(): void
    {
        $slug = 'ht-ctx-taskid-' . uniqid();
        $this->createWorkflow($slug, $this->buildHumanTaskWorkflow([
            'taskTitle' => 'Context task ID',
            'formFields' => [],
        ]));

        $result = $this->engine->startWorkflow($slug, []);
        $instanceId = $result->data['instanceId'];

        $instance = $this->instancesTable->get($instanceId);
        $context = $instance->context;

        $this->assertArrayHasKey('nodes', $context);
        $this->assertArrayHasKey('task1', $context['nodes']);
        $this->assertArrayHasKey('taskId', $context['nodes']['task1']);
        $this->assertEquals('pending', $context['nodes']['task1']['status']);
    }
}
