<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\ActiveWindowBaseEntity;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\App;
use Cake\Core\Container;
use Cake\Core\Plugin;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * CLI command to synchronize ActiveWindow-based entity statuses.
 *
 * Supports dual-path dispatch: when an 'active-window-sync' workflow is active,
 * delegates to the workflow engine; otherwise runs legacy transition logic.
 * Dispatches per kingdom when kingdoms are configured so workflow actions can
 * still use kingdom context without persisted tenant-scoped definitions.
 *
 * Transition entities from Upcoming -> Current and Current -> Expired based on
 * their configured start_on/expires_on window. Intended for scheduled execution
 * via cron or other automation.
 */
class SyncActiveWindowStatusesCommand extends Command
{
    /**
     * Injected dispatcher — null means createTriggerDispatcher() will be called.
     *
     * @var \App\Services\WorkflowEngine\TriggerDispatcher|null
     */
    private ?TriggerDispatcher $triggerDispatcher = null;

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
     * Create a TriggerDispatcher instance.
     *
     * @return \App\Services\WorkflowEngine\TriggerDispatcher
     */
    protected function createTriggerDispatcher(): TriggerDispatcher
    {
        $container = Container::create();
        $engine = new DefaultWorkflowEngine($container);

        return new TriggerDispatcher($engine);
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addOption('dry-run', [
            'short' => 'd',
            'boolean' => true,
            'default' => false,
            'help' => 'Preview changes without saving updates to the database.',
        ]);

        return $parser;
    }

    /**
     * Execute command with dual-path dispatch.
     *
     * @param \Cake\Console\Arguments $args Console arguments instance.
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');

        // Check for active 'active-window-sync' workflow
        if (!$dryRun && $this->dispatchViaWorkflow($io)) {
            return Command::CODE_SUCCESS;
        }

        // Legacy logic
        $io->info('Running legacy active-window sync...');

        return $this->executeLegacy($args, $io);
    }

    /**
     * Attempt to dispatch via the workflow engine.
     *
     * Iterates over all kingdoms when a global workflow definition is active.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return bool True if workflow was dispatched, false to fall back to legacy.
     */
    protected function dispatchViaWorkflow(ConsoleIo $io): bool
    {
        try {
            $branchesTable = TableRegistry::getTableLocator()->get('Branches');
            $kingdoms = $branchesTable->find()
                ->select(['id', 'name'])
                ->where(['type' => 'Kingdom'])
                ->all()
                ->toArray();

            $dispatched = false;
            $dispatcher = null;
            $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
            $fullDef = $defTable->find()
                ->where([
                    'slug' => 'active-window-sync',
                    'is_active' => true,
                    'current_version_id IS NOT' => null,
                ])
                ->contain(['CurrentVersion'])
                ->first();

            if (!$fullDef || !$fullDef->current_version) {
                return false;
            }

            foreach ($kingdoms as $kingdom) {
                $dispatcher = $dispatcher ?? ($this->triggerDispatcher ?? $this->createTriggerDispatcher());
                $io->info(sprintf('Active "active-window-sync" workflow found for kingdom: %s. Dispatching...', $kingdom->name));

                $results = $dispatcher->dispatch('ActiveWindow.SyncTriggered', [
                    'triggered_at' => date('c'),
                    'trigger' => 'cron',
                    'kingdom_id' => $kingdom->id,
                ]);

                $successCount = 0;
                foreach ($results as $result) {
                    if ($result->isSuccess()) {
                        $successCount++;
                    }
                }

                $io->success(sprintf('Workflow dispatched for %s (started %d workflow(s)).', $kingdom->name, $successCount));
                $dispatched = true;
            }

            // If no kingdoms found, try global definition
            if (empty($kingdoms)) {
                $dispatcher = $this->triggerDispatcher ?? $this->createTriggerDispatcher();
                $io->info('Active "active-window-sync" workflow found. Dispatching...');

                $results = $dispatcher->dispatch('ActiveWindow.SyncTriggered', [
                    'triggered_at' => date('c'),
                    'trigger' => 'cron',
                    'kingdom_id' => null,
                ]);

                $successCount = 0;
                foreach ($results as $result) {
                    if ($result->isSuccess()) {
                        $successCount++;
                    }
                }

                $io->success(sprintf('Workflow dispatched (started %d workflow(s)).', $successCount));
                $dispatched = true;
            }

            return $dispatched;
        } catch (Throwable $e) {
            Log::error('SyncActiveWindowStatusesCommand: Workflow dispatch failed: ' . $e->getMessage());
            $io->warning('Workflow dispatch failed, falling back to legacy logic.');

            return false;
        }
    }

    /**
     * Run the legacy active window sync logic.
     *
     * @param \Cake\Console\Arguments $args Console arguments instance.
     * @param \Cake\Console\ConsoleIo $io Console IO instance.
     * @return int
     */
    protected function executeLegacy(Arguments $args, ConsoleIo $io): int
    {
        $dryRun = (bool)$args->getOption('dry-run');
        $now = FrozenTime::now();
        $tableLocator = TableRegistry::getTableLocator();

        $io->out(sprintf('Active window sync started at %s%s', $now->toDateTimeString(), $dryRun ? ' (dry-run)' : ''));

        $aliases = $this->discoverActiveWindowTableAliases($tableLocator);
        if (empty($aliases)) {
            $io->warning('No ActiveWindow-based tables were detected. Nothing to do.');

            return Command::CODE_SUCCESS;
        }

        $overallUpcoming = 0;
        $overallExpired = 0;
        $overallErrors = 0;

        foreach ($aliases as $alias) {
            $table = $tableLocator->get($alias);
            $summary = $this->synchronizeTable($table, $now, $dryRun, $io);

            $overallUpcoming += $summary['upcoming_to_current'];
            $overallExpired += $summary['current_to_expired'];
            $overallErrors += $summary['errors'];

            $io->out(sprintf(
                ' - %s: %d Upcoming→Current, %d Current→Expired%s',
                $alias,
                $summary['upcoming_to_current'],
                $summary['current_to_expired'],
                $summary['errors'] > 0 ? sprintf(' (errors: %d)', $summary['errors']) : '',
            ));
        }

        $io->hr();
        $io->out(sprintf(
            'Summary: %d Upcoming→Current, %d Current→Expired%s',
            $overallUpcoming,
            $overallExpired,
            $overallErrors > 0 ? sprintf(', errors: %d', $overallErrors) : '',
        ));

        return $overallErrors === 0 ? Command::CODE_SUCCESS : Command::CODE_ERROR;
    }

    /**
     * Discover table aliases whose entities extend ActiveWindowBaseEntity.
     *
     * @param \Cake\ORM\Locator\TableLocator $tableLocator Table locator instance.
     * @return array<string>
     */
    protected function discoverActiveWindowTableAliases(TableLocator $tableLocator): array
    {
        $aliases = [];

        foreach ($this->collectTableAliases() as $alias) {
            $aliases = $this->evaluateAlias($tableLocator, $aliases, $alias);
        }

        foreach (Plugin::loaded() as $plugin) {
            foreach ($this->collectTableAliases($plugin) as $alias) {
                $aliases = $this->evaluateAlias($tableLocator, $aliases, $plugin . '.' . $alias);
            }
        }

        sort($aliases);

        return array_values(array_unique($aliases));
    }

    /**
     * Evaluate alias and append when entity inherits ActiveWindowBaseEntity.
     *
     * @param \Cake\ORM\Locator\TableLocator $tableLocator Table locator instance.
     * @param array<string> $aliases Current alias list
     * @param string $alias Alias to evaluate
     * @return array<string>
     */
    protected function evaluateAlias(TableLocator $tableLocator, array $aliases, string $alias): array
    {
        try {
            $table = $tableLocator->get($alias);
            $entityClass = $table->getEntityClass();
            if (
                $entityClass
                && is_subclass_of($entityClass, ActiveWindowBaseEntity::class)
                && $table->getSchema()->hasColumn('status')
            ) {
                $aliases[] = $alias;
            }
            $tableLocator->remove($alias);
        } catch (Throwable $exception) {
            // Ignore loading failures – simply skip invalid aliases.
        }

        return $aliases;
    }

    /**
     * Collect table aliases from the application or a plugin.
     *
     * @param string|null $plugin Plugin name when searching plugin tables.
     * @return array<string>
     */
    protected function collectTableAliases(?string $plugin = null): array
    {
        if ($plugin === null) {
            $paths = App::classPath('Model/Table');
        } else {
            $pluginPath = Plugin::path($plugin)
                . 'src' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'Table' . DIRECTORY_SEPARATOR;
            $paths = [
                $pluginPath,
            ];
        }
        $aliases = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $directoryIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($directoryIterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                if (!str_ends_with($filename, 'Table.php')) {
                    continue;
                }

                $class = substr($filename, 0, -4); // Remove .php
                $alias = substr($class, 0, -5); // Remove Table suffix
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Synchronize statuses for a table whose entity extends ActiveWindowBaseEntity.
     *
     * @param \Cake\ORM\Table $table Target table instance.
     * @param \Cake\I18n\FrozenTime $now Current timestamp reference.
     * @param bool $dryRun Whether to skip persistence.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return array{upcoming_to_current:int,current_to_expired:int,errors:int}
     */
    protected function synchronizeTable(Table $table, FrozenTime $now, bool $dryRun, ConsoleIo $io): array
    {
        $connection = $table->getConnection();
        $summary = [
            'upcoming_to_current' => 0,
            'current_to_expired' => 0,
            'errors' => 0,
        ];

        $schema = $table->getSchema();
        if (!$schema->hasColumn('status') || !$schema->hasColumn('start_on')) {
            return $summary;
        }
        $supportsModifiedBy = $schema->hasColumn('modified_by');

        $connection->transactional(function () use ($table, $now, $dryRun, &$summary, $supportsModifiedBy) {
            $upcoming = $table
                ->find()
                ->where([
                    $table->aliasField('status') => ActiveWindowBaseEntity::UPCOMING_STATUS,
                    $table->aliasField('start_on <=') => $now,
                ])
                ->all();

            foreach ($upcoming as $entity) {
                $summary['upcoming_to_current']++;
                if ($dryRun) {
                    continue;
                }

                $entity->set('status', ActiveWindowBaseEntity::CURRENT_STATUS);
                if ($supportsModifiedBy) {
                    $entity->set('modified_by', 1);
                }
                if (!$table->save($entity, ['atomic' => false, 'checkRules' => false, 'validate' => false])) {
                    $summary['errors']++;
                }
            }

            $current = $table
                ->find()
                ->where([
                    $table->aliasField('status') => ActiveWindowBaseEntity::CURRENT_STATUS,
                    $table->aliasField('expires_on <=') => $now,
                ])
                ->andWhere(function ($exp) use ($table) {
                    return $exp->isNotNull($table->aliasField('expires_on'));
                })
                ->all();

            foreach ($current as $entity) {
                $summary['current_to_expired']++;
                if ($dryRun) {
                    continue;
                }

                $entity->set('status', ActiveWindowBaseEntity::EXPIRED_STATUS);
                if ($supportsModifiedBy) {
                    $entity->set('modified_by', 1);
                }
                if (!$table->save($entity, ['atomic' => false, 'checkRules' => false, 'validate' => false])) {
                    $summary['errors']++;
                }
            }

            return true;
        });

        return $summary;
    }
}
