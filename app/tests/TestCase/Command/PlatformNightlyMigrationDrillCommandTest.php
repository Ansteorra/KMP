<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\PlatformNightlyMigrationDrillCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;

class PlatformNightlyMigrationDrillCommandTest extends TestCase
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
        putenv('KMP_ENABLE_NIGHTLY_MIGRATION_DRILL');
    }

    protected function tearDown(): void
    {
        putenv('KMP_ENABLE_NIGHTLY_MIGRATION_DRILL');
        ConnectionManager::drop('platform');
        if ($this->manifestPath !== null && is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testPlanOnlyValidatesManifestAndRecordsAggregateJob(): void
    {
        $this->insertTenant('alpha', '20260516000000');
        $this->insertTenant('bravo', '20260515000000');
        $command = new PlatformNightlyMigrationDrillCommand();
        $io = $this->stubIo();

        $result = $command->execute($this->args([
            'all' => true,
            'plan-only' => true,
            'manifest' => $this->writeManifest(),
        ]), $io['io']);

        $this->assertSame(0, $result);
        $this->assertStringContainsString('NIGHTLY_MIGRATION_DRILL status=completed', $io['out']->output());
        $jobs = $this->platform()->execute('SELECT * FROM platform_jobs')->fetchAll('assoc');
        $this->assertCount(1, $jobs);
        $this->assertSame('nightly_migration_drill', $jobs[0]['job_type']);
        $this->assertSame('completed', $jobs[0]['status']);
        $parameters = json_decode((string)$jobs[0]['parameters'], true);
        $this->assertSame('plan-only', $parameters['mode']);
        $this->assertSame(['alpha', 'bravo'], $parameters['tenant_slugs']);
        $this->assertSame(2, $parameters['completed']);
        $this->assertSame(0, $parameters['failed']);
    }

    public function testExecutionRequiresFlagAndEnvironmentGate(): void
    {
        $this->insertTenant('alpha', '20260516000000');
        $command = new PlatformNightlyMigrationDrillCommand();
        $io = $this->stubIo();

        $result = $command->execute($this->args([
            'tenant' => 'alpha',
            'manifest' => $this->writeManifest(),
        ]), $io['io']);

        $this->assertSame(1, $result);
        $this->assertStringContainsString('Refusing tenant drill execution', $io['err']->output());
        $job = $this->platform()->execute('SELECT status, last_error FROM platform_jobs')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertStringContainsString('KMP_ENABLE_NIGHTLY_MIGRATION_DRILL=true', (string)$job['last_error']);
    }

    public function testAllowedExecutionRunsStatusAndDryRunProbes(): void
    {
        $this->insertTenant('alpha', '20260516000000');
        putenv('KMP_ENABLE_NIGHTLY_MIGRATION_DRILL=true');
        $calls = [];
        $command = new class ($calls) extends PlatformNightlyMigrationDrillCommand {
            /**
             * @var list<list<string>>
             */
            public array $calls;

            /**
             * @param list<list<string>> $calls
             */
            public function __construct(array &$calls)
            {
                parent::__construct();
                $this->calls =& $calls;
            }

            protected function runTenantCommand(array $commandArgs, ConsoleIo $io): int
            {
                $this->calls[] = $commandArgs;

                return self::CODE_SUCCESS;
            }
        };
        $io = $this->stubIo();

        $result = $command->execute($this->args([
            'tenant' => 'alpha',
            'allow-staging' => true,
            'manifest' => $this->writeManifest(),
        ]), $io['io']);

        $this->assertSame(0, $result);
        $this->assertSame([
            ['--tenant', 'alpha', '--status'],
            ['--tenant', 'alpha', '--marker-only', '--manifest', $this->manifestPath],
            ['--tenant', 'alpha', '--dry-run', '--manifest', $this->manifestPath],
        ], $calls);
        $job = $this->platform()->execute('SELECT status, parameters FROM platform_jobs')->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame('dry-run', $parameters['mode']);
        $this->assertSame(1, $parameters['completed']);
    }

    public function testFailedProbeFailsAggregateJob(): void
    {
        $this->insertTenant('alpha', '20260516000000');
        putenv('KMP_ENABLE_NIGHTLY_MIGRATION_DRILL=true');
        $command = new class extends PlatformNightlyMigrationDrillCommand {
            protected function runTenantCommand(array $commandArgs, ConsoleIo $io): int
            {
                return in_array('--dry-run', $commandArgs, true) ? self::CODE_ERROR : self::CODE_SUCCESS;
            }
        };

        $result = $command->execute($this->args([
            'tenant' => 'alpha',
            'allow-staging' => true,
            'manifest' => $this->writeManifest(),
        ]), $this->stubIo()['io']);

        $this->assertSame(1, $result);
        $job = $this->platform()->execute('SELECT status, parameters FROM platform_jobs')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame(0, $parameters['completed']);
        $this->assertSame(1, $parameters['failed']);
    }

    public function testCommandHelpWorks(): void
    {
        $this->exec('platform nightly_migration_drill --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('safe nightly tenant migration drill');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function args(array $overrides = []): Arguments
    {
        $options = array_merge([
            'manifest' => $this->manifestPath,
            'tenant' => null,
            'all' => false,
            'plan-only' => false,
            'allow-staging' => false,
            'target' => null,
            'date' => null,
            'fail-fast' => false,
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

    private function insertTenant(string $slug, ?string $schemaVersion): string
    {
        $id = Text::uuid();
        $this->platform()->insert('tenants', [
            'id' => $id,
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => 'active',
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
        $this->manifestPath = TESTS . 'nightly_migration_drill_manifest.json';
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
