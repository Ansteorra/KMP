<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\TenantMigrateCommand;
use App\KMP\TenantMetadata;
use App\Services\Platform\TenantMigrationLockException;
use App\Services\Platform\TenantMigrationMarkerResult;
use App\Services\Platform\TenantMigrationMarkerServiceInterface;
use App\Services\Platform\TenantMigrationResult;
use App\Services\Platform\TenantMigrationRunnerInterface;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RuntimeException;

class TenantMigrateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousPlatformConfig = null;

    private ?string $manifestPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createPlatformSchema();
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->manifestPath !== null && is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testSingleTenantSuccessLogsJobAndSchemaVersion(): void
    {
        $tenantId = $this->insertTenant('alpha', 'active', '20260516000000');
        $command = $this->commandWithRunner($this->runnerReturning('20260516009999'));

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(0, $result);
        $job = $this->platform()->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame($tenantId, $job['tenant_id']);
        $this->assertSame('tenant_migration', $job['job_type']);
        $this->assertSame('completed', $job['status']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame('20260516000000', $parameters['previous_schema_version']);
        $this->assertSame('20260516009999', $parameters['result_schema_version']);
        $schemaVersion = $this->platform()
            ->execute('SELECT schema_version FROM tenants WHERE slug = ?', ['alpha'])
            ->fetchColumn(0);
        $this->assertSame('20260516009999', $schemaVersion);
    }

    public function testAdvisoryLockConflictFailsCleanly(): void
    {
        $this->insertTenant('alpha');
        $command = $this->commandWithRunner(new class implements TenantMigrationRunnerInterface {
            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                throw new TenantMigrationLockException('Tenant migration is already running for "alpha".');
            }
        });

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $job = $this->platform()
            ->execute("SELECT status, last_error FROM platform_jobs WHERE job_type = 'tenant_migration'")
            ->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('Tenant migration is already running for "alpha".', $job['last_error']);
    }

    public function testAllTenantsIsolatesFailuresUnlessFailFast(): void
    {
        $alphaId = $this->insertTenant('alpha');
        $bravoId = $this->insertTenant('bravo');
        $this->insertTenant('suspended', 'suspended');
        $command = $this->commandWithRunner(new class implements TenantMigrationRunnerInterface {
            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                if ($tenant->slug === 'alpha') {
                    throw new RuntimeException('alpha failed');
                }

                return new TenantMigrationResult('20260516009999', ['tenant' => $tenant->slug]);
            }
        });

        $result = $command->execute($this->args(['all' => true]), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $jobs = $this->platform()
            ->execute(
                "SELECT tenant_id, status FROM platform_jobs
                WHERE job_type = 'tenant_migration'
                ORDER BY created_at, tenant_id",
            )
            ->fetchAll('assoc');
        $this->assertCount(2, $jobs);
        $jobsByTenant = [];
        foreach ($jobs as $job) {
            $jobsByTenant[(string)$job['tenant_id']] = $job['status'];
        }
        $this->assertSame('failed', $jobsByTenant[$alphaId]);
        $this->assertSame('completed', $jobsByTenant[$bravoId]);
    }

    public function testInactiveTenantRejectedForExplicitMigration(): void
    {
        $this->insertTenant('inactive', 'suspended');
        $command = $this->commandWithRunner($this->runnerReturning('20260516009999'));

        $result = $command->execute($this->args(['tenant' => 'inactive']), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $count = $this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0);
        $this->assertSame(0, (int)$count);
    }

    public function testManifestCompatibilityFailurePreventsTenantMigrationJob(): void
    {
        $this->insertTenant('alpha', 'active', '20260514000000');
        $command = $this->commandWithRunner(new class implements TenantMigrationRunnerInterface {
            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                throw new RuntimeException('Runner should not be called when manifest validation fails.');
            }
        });
        $io = $this->stubIo();

        $result = $command->execute($this->args([
            'tenant' => 'alpha',
            'manifest' => $this->writeManifest(),
        ]), $io['io']);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('below release minimum', implode("\n", $io['err']->messages()));
        $count = $this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0);
        $this->assertSame(0, (int)$count);
    }

    public function testErrorScrubbingRemovesSecretsTokensAndEmails(): void
    {
        $this->insertTenant('alpha');
        $command = $this->commandWithRunner(new class implements TenantMigrationRunnerInterface {
            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                throw new RuntimeException(
                    'Failed for admin@example.test password=hunter2 token=abc123 PGPASSWORD=dbpass Bearer abc.def',
                );
            }
        });

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $lastError = (string)$this->platform()
            ->execute("SELECT last_error FROM platform_jobs WHERE job_type = 'tenant_migration'")
            ->fetchColumn(0);
        $this->assertStringNotContainsString('admin@example.test', $lastError);
        $this->assertStringNotContainsString('hunter2', $lastError);
        $this->assertStringNotContainsString('abc123', $lastError);
        $this->assertStringNotContainsString('dbpass', $lastError);
        $this->assertStringNotContainsString('abc.def', $lastError);
        $this->assertStringContainsString('[redacted-email]', $lastError);
        $this->assertStringContainsString('password=[redacted]', $lastError);
        $this->assertStringContainsString('token=[redacted]', $lastError);
        $this->assertStringContainsString('PGPASSWORD=[redacted]', $lastError);
        $this->assertStringContainsString('Bearer [redacted]', $lastError);
    }

    public function testStatusModeAllowsInactiveTenantWithoutJob(): void
    {
        $this->insertTenant('inactive', 'suspended', '20260516000000');
        $io = $this->stubIo();

        $result = (new TenantMigrateCommand())->execute(
            $this->args(['tenant' => 'inactive', 'status' => true]),
            $io['io'],
        );

        $this->assertSame(0, $result);
        $this->assertStringContainsString('status=suspended', implode("\n", $io['out']->messages()));
        $count = $this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0);
        $this->assertSame(0, (int)$count);
    }

    public function testPreMigrationMarkerRunsBeforeMigrationRunner(): void
    {
        $this->insertTenant('alpha', 'active', '20260516000000');
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationMarkerService($this->markerService($events));
        $command->setMigrationRunner(new class ($events) implements TenantMigrationRunnerInterface {
            /**
             * @var list<string>
             */
            private array $events;

            /**
             * @param list<string> $events
             */
            public function __construct(array &$events)
            {
                $this->events =& $events;
            }

            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                $this->events[] = 'runner';

                return new TenantMigrationResult('20260516009999', ['tenant' => $tenant->slug]);
            }
        });

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(0, $result);
        $this->assertSame(['marker', 'runner'], $events);
    }

    public function testRequiredMarkerFailurePreventsMigrationRunner(): void
    {
        $this->insertTenant('alpha');
        $runnerCalled = false;
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationMarkerService($this->markerService($events, true));
        $command->setMigrationRunner(new class ($runnerCalled) implements TenantMigrationRunnerInterface {
            private bool $runnerCalled;

            public function __construct(bool &$runnerCalled)
            {
                $this->runnerCalled =& $runnerCalled;
            }

            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                $this->runnerCalled = true;

                return new TenantMigrationResult('20260516009999');
            }
        });

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $this->assertFalse($runnerCalled);
        $job = $this->platform()
            ->execute("SELECT status, last_error FROM platform_jobs WHERE job_type = 'tenant_migration'")
            ->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertStringContainsString('Pre-migration marker failed', (string)$job['last_error']);
    }

    public function testRequiredMarkerCanBeExplicitlyBypassed(): void
    {
        $this->insertTenant('alpha');
        $runnerCalled = false;
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationMarkerService($this->markerService($events, true));
        $command->setMigrationRunner(new class ($runnerCalled) implements TenantMigrationRunnerInterface {
            private bool $runnerCalled;

            public function __construct(bool &$runnerCalled)
            {
                $this->runnerCalled =& $runnerCalled;
            }

            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                $this->runnerCalled = true;

                return new TenantMigrationResult('20260516009999');
            }
        });

        $result = $command->execute(
            $this->args(['tenant' => 'alpha', 'skip-pre-migration-marker' => true]),
            $this->stubIo()['io'],
        );

        $this->assertSame(0, $result);
        $this->assertTrue($runnerCalled);
        $this->assertSame([], $events);
    }

    public function testMarkerOnlyCreatesMarkerWithoutRunningMigrations(): void
    {
        $this->insertTenant('alpha', 'active', '20260516000000');
        $runnerCalled = false;
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationMarkerService($this->markerService($events));
        $command->setMigrationRunner(new class ($runnerCalled) implements TenantMigrationRunnerInterface {
            private bool $runnerCalled;

            public function __construct(bool &$runnerCalled)
            {
                $this->runnerCalled =& $runnerCalled;
            }

            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                $this->runnerCalled = true;

                return new TenantMigrationResult('20260516009999');
            }
        });

        $result = $command->execute($this->args(['tenant' => 'alpha', 'marker-only' => true]), $this->stubIo()['io']);

        $this->assertSame(0, $result);
        $this->assertFalse($runnerCalled);
        $this->assertSame(['marker'], $events);
        $parameters = json_decode((string)$this->platform()
            ->execute("SELECT parameters FROM platform_jobs WHERE job_type = 'tenant_migration'")
            ->fetchColumn(0), true);
        $this->assertTrue($parameters['marker_only']);
        $this->assertSame('20260516000000', $parameters['result_schema_version']);
    }

    public function testMarkerMetadataIncludesBackupReferenceAndIsScrubbed(): void
    {
        $this->insertTenant('alpha');
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationMarkerService($this->markerService($events, false, [
            'backup' => [
                'backup_id' => 'backup-123',
                'backup_job_id' => 'job-123',
                'object_uri' => 'local://tenant/alpha/backup-123.pgdump.enc.json',
            ],
            'password' => 'super-secret-password',
            'nested' => ['token' => 'abc123'],
        ]));
        $command->setMigrationRunner($this->runnerReturning('20260516009999'));

        $result = $command->execute($this->args(['tenant' => 'alpha']), $this->stubIo()['io']);

        $this->assertSame(0, $result);
        $parameters = json_decode((string)$this->platform()
            ->execute("SELECT parameters FROM platform_jobs WHERE job_type = 'tenant_migration'")
            ->fetchColumn(0), true);
        $marker = $parameters['pre_migration_marker'];
        $this->assertSame('backup-123', $marker['backup']['backup_id']);
        $this->assertSame('job-123', $marker['backup']['backup_job_id']);
        $this->assertSame('[redacted]', $marker['password']);
        $this->assertSame('[redacted]', $marker['nested']['token']);
        $this->assertStringNotContainsString('super-secret-password', json_encode($parameters));
        $this->assertStringNotContainsString('abc123', json_encode($parameters));
    }

    public function testCommandHelpWorks(): void
    {
        $this->exec('tenant migrate --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Run app and plugin migrations');
    }

    private function commandWithRunner(TenantMigrationRunnerInterface $runner): TenantMigrateCommand
    {
        $events = [];
        $command = new TenantMigrateCommand();
        $command->setMigrationRunner($runner);
        $command->setMigrationMarkerService($this->markerService($events));

        return $command;
    }

    /**
     * @param list<string> $events
     * @param array<string, mixed> $metadata
     */
    private function markerService(
        array &$events,
        bool $fail = false,
        array $metadata = [],
    ): TenantMigrationMarkerServiceInterface {
        return new class ($events, $fail, $metadata) implements TenantMigrationMarkerServiceInterface {
            /**
             * @var list<string>
             */
            private array $events;

            /**
             * @param list<string> $events
             * @param array<string, mixed> $metadata
             */
            public function __construct(
                array &$events,
                private readonly bool $fail,
                private readonly array $metadata,
            ) {
                $this->events =& $events;
            }

            public function createMarker(
                TenantMetadata $tenant,
                array $options,
                string $migrationJobId,
                array $releaseMetadata,
            ): TenantMigrationMarkerResult {
                $this->events[] = 'marker';
                if ($this->fail) {
                    throw new RuntimeException('Pre-migration marker failed password=secret-token');
                }

                $metadata = array_merge([
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'previous_schema_version' => $tenant->schemaVersion,
                    'target_schema_version' => $options['target'] ?? 'latest',
                    'migration_job_id' => $migrationJobId,
                    'marker_timestamp' => '2026-05-16 01:00:00',
                    'release' => $releaseMetadata['release_schema_bounds'] ?? null,
                    'backup' => [
                        'backup_id' => 'backup-alpha',
                        'backup_job_id' => 'backup-job-alpha',
                        'object_uri' => 'local://tenant/alpha/backup-alpha.pgdump.enc.json',
                        'tag' => 'pre-migrate-latest',
                    ],
                ], $this->metadata);

                return new TenantMigrationMarkerResult('marker-job-alpha', $metadata['backup']['backup_id'] ?? null, $metadata);
            }
        };
    }

    private function runnerReturning(string $schemaVersion): TenantMigrationRunnerInterface
    {
        return new class ($schemaVersion) implements TenantMigrationRunnerInterface {
            public function __construct(private readonly string $schemaVersion)
            {
            }

            public function migrate(TenantMetadata $tenant, array $options, ConsoleIo $io): TenantMigrationResult
            {
                return new TenantMigrationResult($this->schemaVersion, ['tenant' => $tenant->slug]);
            }
        };
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function args(array $overrides = []): Arguments
    {
        $options = array_merge([
            'tenant' => null,
            'all' => false,
            'target' => null,
            'date' => null,
            'fake' => false,
            'dry-run' => false,
            'status' => false,
            'marker-only' => false,
            'fail-fast' => false,
            'skip-pre-migration-marker' => false,
            'marker-retention-days' => '30',
            'manifest' => null,
        ], $overrides);

        return new Arguments([], $options, array_keys($options));
    }

    /**
     * @return array{io: \Cake\Console\ConsoleIo, out: \Cake\Console\TestSuite\StubConsoleOutput, err: \Cake\Console\TestSuite\StubConsoleOutput}
     */
    private function stubIo(): array
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();

        return ['io' => new ConsoleIo($out, $err), 'out' => $out, 'err' => $err];
    }

    private function createPlatformSchema(): void
    {
        $this->platform()->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                region TEXT,
                primary_host TEXT,
                db_server TEXT NOT NULL,
                db_name TEXT NOT NULL,
                db_role TEXT NOT NULL,
                key_vault_prefix TEXT,
                schema_version TEXT,
                feature_flags TEXT,
                tenant_config TEXT,
                queue_concurrency_limit INTEGER,
                created_at TEXT,
                activated_at TEXT,
                suspended_at TEXT,
                archived_at TEXT,
                modified_at TEXT
            )',
        );
        $this->platform()->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function insertTenant(
        string $slug,
        string $status = 'active',
        ?string $schemaVersion = null,
    ): string {
        $id = Text::uuid();
        $this->platform()->insert('tenants', [
            'id' => $id,
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => $status,
            'region' => 'test',
            'primary_host' => null,
            'db_server' => 'db.example.test',
            'db_name' => $slug . '_db',
            'db_role' => $slug . '_role',
            'key_vault_prefix' => null,
            'schema_version' => $schemaVersion,
            'feature_flags' => null,
            'tenant_config' => null,
            'queue_concurrency_limit' => 5,
            'created_at' => '2026-05-16 00:00:00',
            'activated_at' => null,
            'suspended_at' => null,
            'archived_at' => null,
            'modified_at' => null,
        ]);

        return $id;
    }

    private function writeManifest(): string
    {
        $this->manifestPath = TESTS . 'release_manifest_test.json';
        file_put_contents($this->manifestPath, json_encode([
            'format_version' => 1,
            'app' => [
                'version' => '2026.05.16-test',
                'image' => 'ghcr.io/example/kmp:2026.05.16-test',
                'digest' => 'sha256:1111111111111111111111111111111111111111111111111111111111111111',
            ],
            'tenant_schema' => [
                'min' => '20260516000000',
                'max' => '20260516009999',
                'compatible_previous' => ['20260515000000'],
            ],
            'migration_policy' => [
                'mode' => 'expand-contract',
                'online' => true,
            ],
            'rollback_notes' => 'Rollback image before contract migrations.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->manifestPath;
    }

    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }
}
