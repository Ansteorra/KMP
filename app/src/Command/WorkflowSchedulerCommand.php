<?php
declare(strict_types=1);

namespace App\Command;

use App\Application;
use App\Model\Entity\WorkflowDefinition;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Container;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cron\CronExpression;
use LogicException;
use Throwable;

/**
 * CLI command to check and dispatch scheduled workflow triggers.
 *
 * Designed to be called frequently (e.g., every minute via system cron).
 * On each invocation it finds workflow definitions with trigger_type='scheduled',
 * evaluates their cron expressions against last_run_at, and dispatches
 * due workflows via TriggerDispatcher. Idempotent — rapid re-runs will not
 * double-trigger because last_run_at is updated before dispatch.
 */
class WorkflowSchedulerCommand extends Command
{
    /**
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     * @param \App\Services\WorkflowEngine\TriggerDispatcher|null $triggerDispatcher Workflow trigger dispatcher
     */
    public function __construct(
        ?CommandFactoryInterface $factory = null,
        ?TriggerDispatcher $triggerDispatcher = null,
    ) {
        parent::__construct($factory);
        $this->triggerDispatcher = $triggerDispatcher;
    }

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'workflow_scheduler';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->setDescription(
            'Check scheduled workflows and dispatch any that are due to run.',
        );

        $parser->addOption('dry-run', [
            'short' => 'd',
            'boolean' => true,
            'default' => false,
            'help' => 'Preview which workflows would be triggered without actually dispatching.',
        ]);

        $parser->addOption('force', [
            'short' => 'f',
            'boolean' => true,
            'default' => false,
            'help' => 'Force all scheduled workflows to run regardless of their schedule.',
        ]);

        return $parser;
    }

    /**
     * Execute the scheduler command.
     *
     * @param \Cake\Console\Arguments $args Console arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $force = (bool)$args->getOption('force');
        $now = new DateTime();

        $io->out(sprintf(
            'Workflow Scheduler running at %s%s',
            $now->format('Y-m-d H:i:s'),
            $dryRun ? ' [DRY RUN]' : '',
        ));

        $definitionsTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $schedulesTable = TableRegistry::getTableLocator()->get('WorkflowSchedules');

        // Find all active scheduled workflow definitions
        $scheduledDefinitions = $definitionsTable->find()
            ->where([
                'WorkflowDefinitions.trigger_type' => WorkflowDefinition::TRIGGER_SCHEDULED,
                'WorkflowDefinitions.is_active' => true,
                'WorkflowDefinitions.current_version_id IS NOT' => null,
                'WorkflowDefinitions.deleted IS' => null,
            ])
            ->contain(['CurrentVersion'])
            ->all();

        if ($scheduledDefinitions->isEmpty()) {
            $io->out('No active scheduled workflows found.');

            return Command::CODE_SUCCESS;
        }

        $io->out(sprintf('Found %d scheduled workflow(s).', $scheduledDefinitions->count()));

        $dispatched = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($scheduledDefinitions as $definition) {
            $result = $this->processScheduledWorkflow(
                $definition,
                $schedulesTable,
                $now,
                $dryRun,
                $force,
                $io,
            );

            if ($result === 'dispatched') {
                $dispatched++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
        }

        $io->out('');
        $io->out(sprintf(
            'Summary: %d dispatched, %d skipped, %d errors',
            $dispatched,
            $skipped,
            $errors,
        ));

        if ($errors > 0) {
            $io->error('Some workflows failed to dispatch.');

            return Command::CODE_ERROR;
        }

        $io->success('Scheduler completed successfully.');

        return Command::CODE_SUCCESS;
    }

    /**
     * Process a single scheduled workflow definition.
     *
     * @param \App\Model\Entity\WorkflowDefinition $definition Workflow definition
     * @param \App\Model\Table\WorkflowSchedulesTable $schedulesTable Schedules table
     * @param \Cake\I18n\DateTime $now Current time
     * @param bool $dryRun Whether this is a dry run
     * @param bool $force Force execution regardless of schedule
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return string 'dispatched', 'skipped', or 'error'
     */
    private function processScheduledWorkflow(
        WorkflowDefinition $definition,
        $schedulesTable,
        DateTime $now,
        bool $dryRun,
        bool $force,
        ConsoleIo $io,
    ): string {
        $triggerConfig = $definition->trigger_config ?? [];
        $cronExpression = $triggerConfig['schedule'] ?? null;

        if (empty($cronExpression)) {
            $io->warning(sprintf(
                '  [%s] No cron schedule configured — skipping.',
                $definition->slug,
            ));

            return 'skipped';
        }

        // Validate the cron expression
        if (!CronExpression::isValidExpression($cronExpression)) {
            $io->error(sprintf(
                '  [%s] Invalid cron expression: %s',
                $definition->slug,
                $cronExpression,
            ));

            return 'error';
        }

        // Get or create the schedule tracking record
        $schedule = $schedulesTable->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->first();

        if (!$schedule) {
            $schedule = $schedulesTable->newEntity([
                'workflow_definition_id' => $definition->id,
                'is_enabled' => true,
            ]);
            $schedulesTable->saveOrFail($schedule);
        }

        if (!$schedule->is_enabled) {
            $io->out(sprintf('  [%s] Schedule disabled — skipping.', $definition->slug));

            return 'skipped';
        }

        // Check if the workflow is due to run
        $cron = new CronExpression($cronExpression);
        $isDue = $force || $this->isDue($cron, $schedule->last_run_at, $now);

        if (!$isDue) {
            $nextRun = $cron->getNextRunDate($now->format('Y-m-d H:i:s'));
            $io->out(sprintf(
                '  [%s] Not due (next: %s) — skipping.',
                $definition->slug,
                $nextRun->format('Y-m-d H:i:s'),
            ));

            // Update next_run_at for visibility
            $schedule->next_run_at = new DateTime($nextRun->format('Y-m-d H:i:s'));
            $schedulesTable->save($schedule);

            return 'skipped';
        }

        if ($dryRun) {
            $io->info(sprintf(
                '  [%s] WOULD dispatch (cron: %s)',
                $definition->slug,
                $cronExpression,
            ));

            return 'dispatched';
        }

        // Update last_run_at BEFORE dispatch to prevent double-triggering
        $schedule->last_run_at = $now;
        $nextRun = $cron->getNextRunDate($now->format('Y-m-d H:i:s'));
        $schedule->next_run_at = new DateTime($nextRun->format('Y-m-d H:i:s'));

        if (!$schedulesTable->save($schedule)) {
            $io->error(sprintf(
                '  [%s] Failed to update schedule record.',
                $definition->slug,
            ));

            return 'error';
        }

        // Dispatch the trigger
        try {
            $dispatcher = $this->getTriggerDispatcher();

            $eventData = [
                'trigger' => 'schedule',
                'schedule' => $cronExpression,
                'scheduledAt' => $now->format('Y-m-d H:i:s'),
                'workflowDefinitionId' => $definition->id,
                'entityType' => $triggerConfig['entityType'] ?? $definition->entity_type,
                'entityQuery' => $triggerConfig['entityQuery'] ?? [],
                'description' => $triggerConfig['description'] ?? $definition->description,
            ];

            $results = $dispatcher->dispatch(
                'Schedule.CronTriggered',
                $eventData,
                null, // system-triggered, no member
            );

            $successCount = 0;
            foreach ($results as $result) {
                if ($result->isSuccess()) {
                    $successCount++;
                }
            }

            $io->success(sprintf(
                '  [%s] Dispatched (cron: %s, started: %d workflow(s))',
                $definition->slug,
                $cronExpression,
                $successCount,
            ));

            return 'dispatched';
        } catch (Throwable $e) {
            Log::error(sprintf(
                'WorkflowScheduler: Failed to dispatch %s: %s',
                $definition->slug,
                $e->getMessage(),
            ));
            $io->error(sprintf(
                '  [%s] Dispatch failed: %s',
                $definition->slug,
                $e->getMessage(),
            ));

            return 'error';
        }
    }

    /**
     * Determine if a scheduled workflow is due to run.
     *
     * Compares the cron expression against the last run time. If the cron
     * expression's previous due date is after the last run time, the
     * workflow is due.
     *
     * @param \Cron\CronExpression $cron Parsed cron expression
     * @param \Cake\I18n\DateTime|null $lastRunAt When the workflow last ran
     * @param \Cake\I18n\DateTime $now Current time
     * @return bool True if the workflow should run
     */
    protected function isDue(CronExpression $cron, ?DateTime $lastRunAt, DateTime $now): bool
    {
        // Never run before — it's due
        if ($lastRunAt === null) {
            return true;
        }

        // Get the most recent time the cron was due (at or before $now)
        $previousDue = $cron->getPreviousRunDate($now->format('Y-m-d H:i:s'), 0, true);
        $previousDueTime = new DateTime($previousDue->format('Y-m-d H:i:s'));

        // If the most recent due time is after our last run, we need to run
        return $previousDueTime > $lastRunAt;
    }

    /**
     * Set a custom TriggerDispatcher (for testing).
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $dispatcher Dispatcher instance
     * @return void
     */
    public function setTriggerDispatcher(TriggerDispatcher $dispatcher): void
    {
        $this->triggerDispatcher = $dispatcher;
    }

    /**
     * Injected dispatcher. Tests may set this manually when constructing the command directly.
     *
     * @var \App\Services\WorkflowEngine\TriggerDispatcher|null
     */
    private ?TriggerDispatcher $triggerDispatcher = null;

    /**
     * Resolve the workflow trigger dispatcher.
     *
     * @return \App\Services\WorkflowEngine\TriggerDispatcher
     */
    private function getTriggerDispatcher(): TriggerDispatcher
    {
        if ($this->triggerDispatcher === null) {
            $container = new Container();
            (new Application(CONFIG))->services($container);
            if (!$container->has(TriggerDispatcher::class)) {
                throw new LogicException('TriggerDispatcher service is not available.');
            }
            $this->triggerDispatcher = $container->get(TriggerDispatcher::class);
        }

        return $this->triggerDispatcher;
    }
}
