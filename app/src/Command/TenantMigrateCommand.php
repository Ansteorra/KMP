<?php
declare(strict_types=1);

namespace App\Command;

use App\KMP\TenantMetadata;
use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\JsonTenantBackupDumper;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Platform\PostgresTenantMigrationLockManager;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use App\Services\Platform\TenantHostResolver;
use App\Services\Platform\TenantMigrateCommandScrubber;
use App\Services\Platform\TenantMigrationCatalog;
use App\Services\Platform\TenantMigrationLockException;
use App\Services\Platform\TenantMigrationLockManagerInterface;
use App\Services\Platform\TenantMigrationMarkerService;
use App\Services\Platform\TenantMigrationMarkerServiceInterface;
use App\Services\Platform\TenantMigrationResult;
use App\Services\Platform\TenantMigrationRunnerInterface;
use App\Services\Platform\TenantMigrationState;
use App\Services\Platform\TenantMigrationStateInspector;
use App\Services\Platform\TenantMigrationStateInspectorInterface;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Command\Command;
use Cake\Command\SchemacacheClearCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use Migrations\Command\MigrateCommand;
use RuntimeException;
use Throwable;

/**
 * Runs tenant application and plugin migrations with platform job logging.
 */
class TenantMigrateCommand extends Command
{
    private const PLATFORM_CONNECTION = 'platform';
    private const TENANT_CONNECTION = TenantConnectionManager::CONNECTION_ALIAS;
    private const JOB_TYPE = 'tenant_migration';
    private const OUTCOME_COMPLETED = 'completed';
    private const OUTCOME_SKIPPED = 'skipped';
    private const OUTCOME_FAILED = 'failed';

    private ?TenantMigrationRunnerInterface $migrationRunner = null;
    private ?TenantMigrationLockManagerInterface $lockManager = null;
    private ?TenantConnectionManager $tenantConnectionManager = null;
    private ?TenantMigrationMarkerServiceInterface $migrationMarkerService = null;
    private ?TenantMigrationStateInspectorInterface $migrationStateInspector = null;
    private ?TenantMigrationCatalog $migrationCatalog = null;

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'tenant migrate';
    }

    /**
     * Override migration execution for tests.
     */
    public function setMigrationRunner(TenantMigrationRunnerInterface $migrationRunner): void
    {
        $this->migrationRunner = $migrationRunner;
    }

    /**
     * Override advisory locking for tests.
     */
    public function setLockManager(TenantMigrationLockManagerInterface $lockManager): void
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Override tenant connection scoping for tests.
     */
    public function setTenantConnectionManager(TenantConnectionManager $tenantConnectionManager): void
    {
        $this->tenantConnectionManager = $tenantConnectionManager;
    }

    /**
     * Override pre-migration marker creation for tests.
     */
    public function setMigrationMarkerService(TenantMigrationMarkerServiceInterface $migrationMarkerService): void
    {
        $this->migrationMarkerService = $migrationMarkerService;
    }

    /**
     * Override tenant migration inspection for tests.
     */
    public function setMigrationStateInspector(TenantMigrationStateInspectorInterface $migrationStateInspector): void
    {
        $this->migrationStateInspector = $migrationStateInspector;
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Run pending app and plugin migrations across the tenant fleet.')
            ->addOption('tenant', [
                'short' => 't',
                'help' => 'Tenant slug to migrate.',
            ])
            ->addOption('all', [
                'help' => 'Migrate all active tenants ordered by slug.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('include-suspended', [
                'help' => 'Include suspended tenants when selecting the fleet or an explicit tenant.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('target', [
                'help' => 'Target migration version.',
            ])
            ->addOption('date', [
                'help' => 'Target migration date.',
            ])
            ->addOption('fake', [
                'help' => 'Mark migrations as run without executing them.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('dry-run', [
                'short' => 'x',
                'help' => 'Print migration SQL without executing changes.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('status', [
                'help' => 'Show tenant migration status without taking locks or running migrations.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('marker-only', [
                'help' => 'Create the pre-migration recovery marker and stop before running migrations.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('fail-fast', [
                'help' => 'Stop --all after the first tenant failure.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('skip-pre-migration-marker', [
                'help' => 'Bypass the required pre-migration recovery marker. Use only for emergency/manual recovery.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('marker-retention-days', [
                'help' => 'Retention period for the pre-migration logical backup marker.',
                'default' => '30',
            ])
            ->addOption('manifest', [
                'help' => 'Optional release manifest JSON to validate before migrating tenants.',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        try {
            $this->assertSelector($args);
            $tenants = $this->resolveTenants($args);
            if ((bool)$args->getOption('status')) {
                $this->printStatus($tenants, $io);

                return self::CODE_SUCCESS;
            }

            $options = $this->migrationOptions($args);
            $this->assertMarkerOnlyOptions($options);
            $this->assertReleaseCompatibility($tenants, $options);
            $failed = 0;
            $completed = 0;
            $skipped = 0;
            foreach ($tenants as $tenant) {
                $outcome = $this->migrateTenant($tenant, $options, $io);
                if ($outcome === self::OUTCOME_COMPLETED) {
                    $completed++;
                } elseif ($outcome === self::OUTCOME_SKIPPED) {
                    $skipped++;
                } else {
                    $failed++;
                    if ((bool)$args->getOption('fail-fast')) {
                        break;
                    }
                }
            }

            $io->out(sprintf(
                'Tenant migration summary: %d completed, %d already current, %d failed.',
                $completed,
                $skipped,
                $failed,
            ));

            return $failed === 0 ? self::CODE_SUCCESS : self::CODE_ERROR;
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function migrateTenant(TenantMetadata $tenant, array $options, ConsoleIo $io): string
    {
        $jobId = Text::uuid();
        $this->insertJob($jobId, $tenant, $options);
        $io->out(sprintf('Inspecting tenant %s...', $tenant->slug));

        try {
            $this->assertTenantDatabaseConfig($tenant);
            $beforeState = $this->migrationStateInspector()->inspect($tenant);
            $this->assertNoMigrationHistoryDrift($tenant, $beforeState);
            if ($this->isStandardReleaseMigration($options) && $beforeState->isCurrent()) {
                $finishedAt = $this->now();
                $parameters = TenantMigrateCommandScrubber::scrubMetadata(array_merge($options, [
                    'tenant_slug' => $tenant->slug,
                    'previous_schema_version' => $tenant->schemaVersion,
                    'migration_state_before' => $beforeState->toMetadata(),
                    'result_schema_version' => $beforeState->targetVersion,
                    'result' => ['already_current' => true],
                ]));
                $this->platform()->update('platform_jobs', [
                    'status' => 'completed',
                    'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
                    'finished_at' => $finishedAt,
                    'modified_at' => $finishedAt,
                ], ['id' => $jobId]);
                $this->platform()->update('tenants', [
                    'schema_version' => $beforeState->targetVersion,
                    'modified_at' => $finishedAt,
                ], ['id' => $tenant->id]);
                TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);
                $io->out(sprintf(
                    'Tenant %s is already current at schema %s; no backup or migration was needed.',
                    $tenant->slug,
                    $beforeState->targetVersion,
                ));

                return self::OUTCOME_SKIPPED;
            }

            $io->out(sprintf('Migrating tenant %s...', $tenant->slug));
            $marker = null;
            if (!(bool)($options['skip_pre_migration_marker'] ?? false) && !(bool)($options['dry_run'] ?? false)) {
                $marker = $this->migrationMarkerService()->createMarker($tenant, $options, $jobId);
            }
            $result = (bool)($options['marker_only'] ?? false)
                ? new TenantMigrationResult($tenant->schemaVersion, ['marker_only' => true])
                : $this->runTenantMigration($tenant, $options, $io);
            $afterState = null;
            if ($this->isStandardReleaseMigration($options)) {
                $afterState = $this->migrationStateInspector()->inspect($tenant);
                $this->assertTenantCurrent($tenant, $afterState);
                $result = new TenantMigrationResult($afterState->targetVersion, array_merge(
                    $result->metadata,
                    ['verified_current' => true],
                ));
            }
            $finishedAt = $this->now();
            $parameters = array_merge($options, [
                'tenant_slug' => $tenant->slug,
                'previous_schema_version' => $tenant->schemaVersion,
                'migration_state_before' => $beforeState->toMetadata(),
                'migration_state_after' => $afterState?->toMetadata(),
                'pre_migration_marker' => $marker?->metadata,
                'result_schema_version' => $result->schemaVersion,
                'result' => $result->metadata,
            ]);
            $parameters = TenantMigrateCommandScrubber::scrubMetadata($parameters);
            $this->platform()->update('platform_jobs', [
                'status' => 'completed',
                'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);
            if (!(bool)($options['dry_run'] ?? false)) {
                $this->platform()->update('tenants', [
                    'schema_version' => $result->schemaVersion,
                    'modified_at' => $finishedAt,
                ], ['id' => $tenant->id]);
                TenantHostResolver::clearCache(self::PLATFORM_CONNECTION);
            }
            $io->success(sprintf(
                'Tenant %s migrated to schema %s.',
                $tenant->slug,
                $result->schemaVersion ?? 'unknown',
            ));

            return self::OUTCOME_COMPLETED;
        } catch (Throwable $e) {
            $message = self::scrubError($e->getMessage());
            $finishedAt = $this->now();
            $this->platform()->update('platform_jobs', [
                'status' => 'failed',
                'last_error' => $message,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);
            $io->err(sprintf('Tenant %s migration failed: %s', $tenant->slug, $message));

            return self::OUTCOME_FAILED;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runTenantMigration(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
    {
        if ($this->migrationRunner !== null) {
            return $this->migrationRunner->migrate($tenant, $options, $io);
        }

        return $this->tenantConnectionManager()->withTenant(
            $tenant,
            function () use ($tenant, $options, $io): TenantMigrationResult {
                /** @var \Cake\Database\Connection $connection */
                $connection = ConnectionManager::get(self::TENANT_CONNECTION);
                if (!$connection->getDriver() instanceof Postgres) {
                    throw new RuntimeException('Tenant migrations require a PostgreSQL tenant datasource.');
                }

                $lock = $this->lockManager()->acquire($connection, $tenant);
                if (!$lock->acquired) {
                    throw new TenantMigrationLockException(
                        sprintf('Tenant migration is already running for "%s".', $tenant->slug),
                    );
                }

                try {
                    $this->runCakeMigrations($options, $io);

                    return new TenantMigrationResult($this->readSchemaVersion(), [
                        'dry_run' => (bool)($options['dry_run'] ?? false),
                        'fake' => (bool)($options['fake'] ?? false),
                    ]);
                } finally {
                    $lock->release();
                }
            },
        );
    }

    /**
     * @param array{target?: string|null, date?: string|null, fake?: bool, dry_run?: bool, manifest?: string|null} $options
     */
    private function runCakeMigrations(array $options, ConsoleIo $io): void
    {
        $this->executeCommand(SchemacacheClearCommand::class, ['--connection', self::TENANT_CONNECTION], $io);
        $this->executeMigrationCommand(new MigrateCommand(), $this->migrationCommandArgs($options), $io, 'application');

        foreach ($this->pluginsWithMigrations() as $plugin) {
            $args = array_merge($this->migrationCommandArgs($options), ['--plugin', $plugin]);
            $this->executeMigrationCommand(new MigrateCommand(), $args, $io, $plugin);
        }
    }

    /**
     * @param array{target?: string|null, date?: string|null, fake?: bool, dry_run?: bool, manifest?: string|null} $options
     * @return list<string>
     */
    private function migrationCommandArgs(array $options): array
    {
        $commandArgs = ['migrate', '--connection', self::TENANT_CONNECTION, '--no-lock'];
        foreach (['target', 'date'] as $option) {
            if (($options[$option] ?? null) !== null && $options[$option] !== '') {
                $commandArgs[] = '--' . $option;
                $commandArgs[] = (string)$options[$option];
            }
        }
        foreach (['fake' => 'fake', 'dry_run' => 'dry-run'] as $option => $argument) {
            if ((bool)($options[$option] ?? false)) {
                $commandArgs[] = '--' . $argument;
            }
        }

        return $commandArgs;
    }

    /**
     * @param list<string> $args
     */
    private function executeMigrationCommand(MigrateCommand $command, array $args, ConsoleIo $io, string $label): void
    {
        $result = (int)$this->executeCommand($command, $args, $io);
        if ($result !== self::CODE_SUCCESS) {
            throw new RuntimeException(sprintf('Tenant %s migrations failed.', $label));
        }
    }

    /**
     * @return list<string>
     */
    private function pluginsWithMigrations(): array
    {
        $plugins = [];
        foreach (Plugin::getCollection() as $name => $plugin) {
            if (is_dir($plugin->getPath() . 'config' . DS . 'Migrations')) {
                $plugins[] = (string)$name;
            }
        }

        return $plugins;
    }

    /**
     * Read the current Phinx schema version from the tenant database.
     */
    private function readSchemaVersion(): ?string
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::TENANT_CONNECTION);

        return $this->migrationCatalog()->inspect($connection)->currentVersion;
    }

    /**
     * @return list<\App\KMP\TenantMetadata>
     */
    private function resolveTenants(Arguments $args): array
    {
        if ((bool)$args->getOption('all')) {
            $includeSuspended = (bool)$args->getOption('include-suspended');
            $rows = $includeSuspended
                ? $this->platform()->execute(
                    'SELECT * FROM tenants WHERE status IN (:active, :suspended) ORDER BY slug',
                    ['active' => 'active', 'suspended' => 'suspended'],
                )->fetchAll('assoc')
                : $this->platform()->execute(
                    'SELECT * FROM tenants WHERE status = :active ORDER BY slug',
                    ['active' => 'active'],
                )->fetchAll('assoc');

            return array_map(
                static fn(array $row): TenantMetadata => TenantMetadata::fromPlatformRow($row),
                $rows,
            );
        }

        $slug = strtolower(trim((string)$args->getOption('tenant')));
        $this->assertSafeSlug($slug);
        $row = $this->platform()->execute(
            'SELECT * FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $slug],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Tenant "%s" was not found.', $slug));
        }
        $allowedStatuses = (bool)$args->getOption('include-suspended')
            ? ['active', 'suspended']
            : ['active'];
        if (!(bool)$args->getOption('status') && !in_array((string)$row['status'], $allowedStatuses, true)) {
            throw new RuntimeException(sprintf(
                'Tenant "%s" status %s is not selected for migration.',
                $slug,
                (string)$row['status'],
            ));
        }

        return [TenantMetadata::fromPlatformRow($row)];
    }

    /**
     * @param list<\App\KMP\TenantMetadata> $tenants
     */
    private function printStatus(array $tenants, ConsoleIo $io): void
    {
        $io->out('Tenant migration status:');
        foreach ($tenants as $tenant) {
            $io->out(sprintf(
                '- %s: status=%s schema=%s db=%s/%s',
                $tenant->slug,
                $tenant->status,
                $tenant->schemaVersion ?? 'unknown',
                $tenant->dbServer,
                $tenant->dbName,
            ));
        }
    }

    /**
     * Validate that exactly one target selector was supplied.
     */
    private function assertSelector(Arguments $args): void
    {
        $hasTenant = trim((string)$args->getOption('tenant')) !== '';
        $hasAll = (bool)$args->getOption('all');
        if ($hasTenant === $hasAll) {
            throw new RuntimeException('Specify exactly one of --tenant or --all.');
        }
        if ($args->getOption('target') !== null && $args->getOption('date') !== null) {
            throw new RuntimeException('Use either --target or --date, not both.');
        }
    }

    /**
     * Validate tenant slug syntax before querying metadata.
     */
    private function assertSafeSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new RuntimeException('Invalid tenant slug.');
        }
    }

    /**
     * Fail closed when required tenant database metadata is missing.
     */
    private function assertTenantDatabaseConfig(TenantMetadata $tenant): void
    {
        if (trim($tenant->dbServer) === '' || trim($tenant->dbName) === '' || trim($tenant->dbRole) === '') {
            throw new RuntimeException(sprintf('Tenant "%s" is missing database connection metadata.', $tenant->slug));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function migrationOptions(Arguments $args): array
    {
        return [
            'target' => $args->getOption('target') === null ? null : (string)$args->getOption('target'),
            'date' => $args->getOption('date') === null ? null : (string)$args->getOption('date'),
            'fake' => (bool)$args->getOption('fake'),
            'dry_run' => (bool)$args->getOption('dry-run'),
            'marker_only' => (bool)$args->getOption('marker-only'),
            'manifest' => $args->getOption('manifest') === null ? null : (string)$args->getOption('manifest'),
            'include_suspended' => (bool)$args->getOption('include-suspended'),
            'skip_pre_migration_marker' => (bool)$args->getOption('skip-pre-migration-marker'),
            'marker_retention_days' => max(1, (int)$args->getOption('marker-retention-days')),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertMarkerOnlyOptions(array $options): void
    {
        if (!(bool)($options['marker_only'] ?? false)) {
            return;
        }
        if ((bool)($options['dry_run'] ?? false)) {
            throw new RuntimeException('Use either --marker-only or --dry-run, not both.');
        }
        if ((bool)($options['skip_pre_migration_marker'] ?? false)) {
            throw new RuntimeException('--marker-only requires the pre-migration marker.');
        }
    }

    /**
     * @param list<\App\KMP\TenantMetadata> $tenants
     * @param array<string, mixed> $options
     */
    private function assertReleaseCompatibility(array $tenants, array $options): void
    {
        $manifestPath = $options['manifest'] ?? null;
        if ($manifestPath === null || trim($manifestPath) === '') {
            return;
        }

        $manifest = ReleaseManifest::fromFile((string)$manifestPath);
        $checker = new ReleaseCompatibilityChecker();
        foreach ($tenants as $tenant) {
            $checker->assertTenantCompatible($tenant->schemaVersion, $manifest, $tenant->slug);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function insertJob(string $jobId, TenantMetadata $tenant, array $options): void
    {
        $now = $this->now();
        $parameters = array_merge($options, [
            'tenant_slug' => $tenant->slug,
            'previous_schema_version' => $tenant->schemaVersion,
        ]);
        $parameters = TenantMigrateCommandScrubber::scrubMetadata($parameters);
        $this->platform()->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $tenant->id,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => 'running',
            'idempotency_key' => null,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }

    /**
     * Remove secret-like values before writing platform job errors.
     */
    public static function scrubError(string $message): string
    {
        return TenantMigrateCommandScrubber::scrubString(PlatformScheduleRunner::scrubError($message));
    }

    /**
     * Get the tenant connection manager.
     */
    private function tenantConnectionManager(): TenantConnectionManager
    {
        return $this->tenantConnectionManager ??= new TenantConnectionManager(SecretStoreFactory::fromConfig());
    }

    /**
     * Get the advisory lock manager.
     */
    private function lockManager(): TenantMigrationLockManagerInterface
    {
        return $this->lockManager ??= new PostgresTenantMigrationLockManager();
    }

    /**
     * Get the pre-migration marker service.
     */
    private function migrationMarkerService(): TenantMigrationMarkerServiceInterface
    {
        if ($this->migrationMarkerService !== null) {
            return $this->migrationMarkerService;
        }

        return $this->migrationMarkerService = new TenantMigrationMarkerService(
            $this->platform(),
            new TenantBackupService(
                $this->platform(),
                SecretStoreFactory::fromConfig(),
                new JsonTenantBackupDumper($this->tenantConnectionManager()),
                new TenantBackupEncryptor(),
                BackupStorageFactory::tenant(),
            ),
        );
    }

    /**
     * Get the release migration-state inspector.
     */
    private function migrationStateInspector(): TenantMigrationStateInspectorInterface
    {
        return $this->migrationStateInspector ??= new TenantMigrationStateInspector(
            $this->tenantConnectionManager(),
            $this->migrationCatalog(),
        );
    }

    /**
     * Get the migration catalog shared by planning and result verification.
     */
    private function migrationCatalog(): TenantMigrationCatalog
    {
        return $this->migrationCatalog ??= new TenantMigrationCatalog();
    }

    /**
     * Standard deploy migrations can be planned, skipped, and verified against latest.
     *
     * @param array<string, mixed> $options
     */
    private function isStandardReleaseMigration(array $options): bool
    {
        return empty($options['target'])
            && empty($options['date'])
            && !(bool)($options['fake'] ?? false)
            && !(bool)($options['dry_run'] ?? false)
            && !(bool)($options['marker_only'] ?? false);
    }

    /**
     * Refuse to operate when database history contains migrations absent from this image.
     */
    private function assertNoMigrationHistoryDrift(TenantMetadata $tenant, TenantMigrationState $state): void
    {
        if ($state->unexpectedVersions === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Tenant "%s" has migration history absent from this release: %s.',
            $tenant->slug,
            json_encode($state->unexpectedVersions, JSON_UNESCAPED_SLASHES),
        ));
    }

    /**
     * Verify that migration execution reached every app and plugin target.
     */
    private function assertTenantCurrent(TenantMetadata $tenant, TenantMigrationState $state): void
    {
        $this->assertNoMigrationHistoryDrift($tenant, $state);
        if ($state->isCurrent()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Tenant "%s" still has pending migrations after execution: %s.',
            $tenant->slug,
            json_encode($state->pendingVersions, JSON_UNESCAPED_SLASHES),
        ));
    }

    /**
     * Get the platform metadata connection.
     */
    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get(self::PLATFORM_CONNECTION);

        return $connection;
    }

    /**
     * Current UTC timestamp for platform rows.
     */
    private function now(): string
    {
        return DateTime::now('UTC')->format('Y-m-d H:i:s');
    }
}
