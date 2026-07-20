<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\WorkflowSchedulerCommand;
use App\Model\Entity\WorkflowDefinition;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\BaseTestCase;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for WorkflowSchedulerCommand.
 */
class WorkflowSchedulerCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private $defTable;
    private $versionsTable;
    private $schedulesTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $this->versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $this->schedulesTable = TableRegistry::getTableLocator()->get('WorkflowSchedules');
    }

    /**
     * Create a scheduled workflow definition with a published version.
     *
     * @param string $slug Unique slug
     * @param string $cronExpression Cron schedule expression
     * @param array $extraTriggerConfig Additional trigger config
     * @return \App\Model\Entity\WorkflowDefinition
     */
    private function createScheduledWorkflow(
        string $slug,
        string $cronExpression = '0 2 * * *',
        array $extraTriggerConfig = [],
    ): WorkflowDefinition {
        $triggerConfig = array_merge([
            'schedule' => $cronExpression,
            'description' => 'Test scheduled workflow',
        ], $extraTriggerConfig);

        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED,
            'trigger_config' => $triggerConfig,
            'entity_type' => 'Members',
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger1' => [
                        'type' => 'trigger',
                        'config' => ['event' => 'Schedule.CronTriggered'],
                        'outputs' => [['port' => 'default', 'target' => 'end1']],
                    ],
                    'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $def->current_version_id = $version->id;
        $this->defTable->saveOrFail($def);

        return $def;
    }

    /**
     * Test: command finds scheduled workflows and reports them.
     */
    public function testCommandFindsScheduledWorkflows(): void
    {
        $slug = 'sched-find-' . uniqid();
        $this->createScheduledWorkflow($slug, '* * * * *');

        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('Found');
        $this->assertOutputContains('WOULD dispatch');
    }

    /**
     * Test: command skips workflows not yet due.
     */
    public function testCommandSkipsWorkflowsNotYetDue(): void
    {
        $slug = 'sched-skip-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '0 2 * * *');

        // Create a schedule record showing it ran very recently
        $schedule = $this->schedulesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'last_run_at' => new DateTime('now'),
            'is_enabled' => true,
        ]);
        $this->schedulesTable->saveOrFail($schedule);

        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('Not due');
    }

    /**
     * Test: command dispatches trigger for due workflows.
     */
    public function testCommandDispatchesTriggerForDueWorkflows(): void
    {
        $slug = 'sched-due-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '* * * * *');

        // Create schedule with last_run_at far in the past
        $schedule = $this->schedulesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'last_run_at' => new DateTime('2020-01-01 00:00:00'),
            'is_enabled' => true,
        ]);
        $this->schedulesTable->saveOrFail($schedule);

        // Use dry-run to verify it WOULD dispatch (avoids needing full engine)
        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('WOULD dispatch');
    }

    /**
     * Test: command updates last_run_at after execution.
     */
    public function testCommandUpdatesLastRunAtAfterExecution(): void
    {
        $slug = 'sched-update-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '* * * * *');
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('dispatchTrigger')
            ->willReturn([new ServiceResult(true, 'Workflow started')]);
        $command = new WorkflowSchedulerCommand(
            null,
            new TriggerDispatcher($engine),
        );
        $args = new Arguments([], ['dry-run' => false, 'force' => false], ['dry-run', 'force']);

        // No schedule record yet — first successful run records completion.
        $result = $command->execute(
            $args,
            new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput()),
        );

        // Verify a schedule record was created and last_run_at set
        $this->assertSame(Command::CODE_SUCCESS, $result);
        $schedule = $this->schedulesTable->find()
            ->where(['workflow_definition_id' => $def->id])
            ->first();

        $this->assertNotNull($schedule, 'Schedule record should be created');
        $this->assertNotNull($schedule->last_run_at, 'last_run_at should be set');
        $this->assertNotNull($schedule->next_run_at, 'next_run_at should be set');
    }

    /**
     * Test: command handles invalid cron expression gracefully.
     */
    public function testCommandHandlesInvalidCronExpression(): void
    {
        $slug = 'sched-invalid-' . uniqid();
        $this->createScheduledWorkflow($slug, 'invalid-cron');

        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitError();
        $this->assertErrorContains('Invalid cron expression');
    }

    /**
     * Test: command handles missing schedule config.
     */
    public function testCommandHandlesMissingScheduleConfig(): void
    {
        // Create a workflow with trigger_type=scheduled but no schedule in config
        $slug = 'sched-noconfig-' . uniqid();
        $def = $this->defTable->newEntity([
            'name' => 'Test: ' . $slug,
            'slug' => $slug,
            'trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED,
            'trigger_config' => [],
            'is_active' => true,
        ]);
        $this->defTable->saveOrFail($def);

        $version = $this->versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger1' => ['type' => 'trigger', 'config' => [], 'outputs' => [['port' => 'default', 'target' => 'end1']]],
                    'end1' => ['type' => 'end', 'config' => [], 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $this->versionsTable->saveOrFail($version);

        $def->current_version_id = $version->id;
        $this->defTable->saveOrFail($def);

        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitSuccess();
        // Warning goes to stderr via $io->warning()
        $this->assertOutputContains('skipped');
    }

    /**
     * Test: command skips disabled schedules.
     */
    public function testCommandSkipsDisabledSchedules(): void
    {
        $slug = 'sched-disabled-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '* * * * *');

        $schedule = $this->schedulesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'is_enabled' => false,
        ]);
        $this->schedulesTable->saveOrFail($schedule);

        $this->exec('workflow_scheduler --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('disabled');
    }

    /**
     * Test: idempotency — running twice quickly does not double-trigger.
     */
    public function testIdempotency(): void
    {
        $slug = 'sched-idempotent-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '0 2 * * *');
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('dispatchTrigger')
            ->willReturn([new ServiceResult(true, 'Workflow started')]);
        $command = new WorkflowSchedulerCommand(
            null,
            new TriggerDispatcher($engine),
        );
        $args = new Arguments([], ['dry-run' => false, 'force' => false], ['dry-run', 'force']);

        // First run — due because no last_run_at
        $result = $command->execute(
            $args,
            new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput()),
        );

        $this->assertSame(Command::CODE_SUCCESS, $result);
        $schedule = $this->schedulesTable->find()
            ->where(['workflow_definition_id' => $def->id])
            ->first();

        $this->assertNotNull($schedule);

        // Second run — should skip because just ran
        $this->exec('workflow_scheduler --dry-run');

        $this->assertOutputContains('Not due');
    }

    public function testFailedDispatchReleasesClaimWithoutConsumingRun(): void
    {
        $this->defTable->updateAll(
            ['is_active' => false],
            ['trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED],
        );
        $slug = 'sched-retry-' . uniqid();
        $definition = $this->createScheduledWorkflow($slug, '* * * * *');
        $lastRunAt = new DateTime('2020-01-01 00:00:00');
        $schedule = $this->schedulesTable->newEntity([
            'workflow_definition_id' => $definition->id,
            'last_run_at' => $lastRunAt,
            'is_enabled' => true,
        ]);
        $this->schedulesTable->saveOrFail($schedule);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('dispatchTrigger')
            ->willReturn([new ServiceResult(false, 'dispatch failed')]);
        $command = new WorkflowSchedulerCommand(
            null,
            new TriggerDispatcher($engine),
        );
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $args = new Arguments([], ['dry-run' => false, 'force' => false], ['dry-run', 'force']);

        $result = $command->execute($args, new ConsoleIo($out, $err));

        $this->assertSame(Command::CODE_ERROR, $result);
        $updated = $this->schedulesTable->get($schedule->id);
        $this->assertEquals($lastRunAt, $updated->last_run_at);
        $this->assertNull($updated->claim_token);
        $this->assertNull($updated->claimed_at);
    }

    /**
     * Test: command outputs summary with no scheduled workflows.
     */
    public function testNoScheduledWorkflows(): void
    {
        // Clean any existing scheduled definitions (within our transaction)
        $this->defTable->deleteAll([
            'trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED,
        ]);

        $this->exec('workflow_scheduler');

        $this->assertExitSuccess();
        $this->assertOutputContains('No active scheduled workflows found');
    }

    /**
     * Test: force flag bypasses schedule check.
     */
    public function testForceFlag(): void
    {
        $slug = 'sched-force-' . uniqid();
        $def = $this->createScheduledWorkflow($slug, '0 0 1 1 *'); // Once a year

        // Set last_run_at to just now — normally would skip
        $schedule = $this->schedulesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'last_run_at' => new DateTime('now'),
            'is_enabled' => true,
        ]);
        $this->schedulesTable->saveOrFail($schedule);

        $this->exec('workflow_scheduler --dry-run --force');

        $this->assertExitSuccess();
        $this->assertOutputContains('WOULD dispatch');
    }
}
