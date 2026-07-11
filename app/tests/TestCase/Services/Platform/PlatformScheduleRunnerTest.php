<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Platform\PlatformScheduleDispatcherInterface;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\TenantConnectionManager;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RuntimeException;

class PlatformScheduleRunnerTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousPlatformConfig = null;

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
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testEnabledPlatformScheduleCreatesCompletedJob(): void
    {
        $this->insertSchedule('daily-maintenance');
        $runner = new PlatformScheduleRunner($this->noopDispatcher());

        $result = $runner->run('daily-maintenance');

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['jobsCreated']);

        $job = $this->platform()->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $this->assertNull($job['tenant_id']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame('daily-maintenance', $parameters['schedule_name']);
        $this->assertArrayNotHasKey('payload', $parameters);
    }

    public function testDisabledScheduleSkipsWithoutJob(): void
    {
        $this->insertSchedule('disabled-maintenance', ['enabled' => 0]);
        $runner = new PlatformScheduleRunner($this->noopDispatcher());

        $result = $runner->run('disabled-maintenance');

        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, $result['jobsCreated']);
        $count = $this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetch()[0];
        $this->assertSame(0, (int)$count);
    }

    public function testAllActiveTenantsIsolatesFailures(): void
    {
        $alphaId = $this->insertTenant('alpha', 'active');
        $bravoId = $this->insertTenant('bravo', 'active');
        $this->insertTenant('suspended', 'suspended');
        $this->insertSchedule('tenant-sweep', [
            'tenant_scope' => PlatformScheduleRunner::SCOPE_ALL_ACTIVE_TENANTS,
        ]);
        $dispatcher = new class implements PlatformScheduleDispatcherInterface {
            public function dispatch(array $schedule, ?TenantMetadata $tenant): void
            {
                if ($tenant?->slug === 'alpha') {
                    throw new RuntimeException('Failed while processing alpha@example.test');
                }
            }
        };
        $runner = new PlatformScheduleRunner($dispatcher);

        $result = $runner->run('tenant-sweep');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(2, $result['jobsCreated']);

        $jobs = $this->platform()
            ->execute('SELECT tenant_id, status, last_error FROM platform_jobs')
            ->fetchAll('assoc');
        $jobsByTenant = [];
        foreach ($jobs as $job) {
            $jobsByTenant[(string)$job['tenant_id']] = $job;
        }
        $this->assertCount(2, $jobs);
        $this->assertSame('failed', $jobsByTenant[$alphaId]['status']);
        $this->assertSame('Failed while processing [redacted-email]', $jobsByTenant[$alphaId]['last_error']);
        $this->assertSame('completed', $jobsByTenant[$bravoId]['status']);
    }

    public function testAllActiveTenantsRunsWithTenantContextAndCleansUp(): void
    {
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('bravo', 'active');
        $this->insertSchedule('tenant-command', [
            'tenant_scope' => PlatformScheduleRunner::SCOPE_ALL_ACTIVE_TENANTS,
            'options' => json_encode(['requires_tenant_connection' => true], JSON_THROW_ON_ERROR),
        ]);
        $seen = [];
        $cleanupStates = [];
        $dispatcher = new class ($seen) implements PlatformScheduleDispatcherInterface {
            /**
             * @var list<string>
             */
            private array $seen;

            /**
             * @param list<string> $seen Seen tenant slugs
             */
            public function __construct(array &$seen)
            {
                $this->seen =& $seen;
            }

            public function dispatch(array $schedule, ?TenantMetadata $tenant): void
            {
                $this->seen[] = TenantContext::slug();
            }
        };
        $connectionManager = new class ($cleanupStates) extends TenantConnectionManager {
            /**
             * @var list<bool>
             */
            private array $cleanupStates;

            /**
             * @param list<bool> $cleanupStates Cleanup checks
             */
            public function __construct(array &$cleanupStates)
            {
                parent::__construct(new ArraySecretStore([]));
                $this->cleanupStates =& $cleanupStates;
            }

            public function withTenant(TenantMetadata $tenant, callable $callback): mixed
            {
                try {
                    return TenantContext::with($tenant, $callback);
                } finally {
                    $this->cleanupStates[] = TenantContext::tryCurrent() === null;
                }
            }
        };
        $runner = new PlatformScheduleRunner($dispatcher, $connectionManager);

        $result = $runner->run('tenant-command');

        $this->assertSame('completed', $result['status']);
        $this->assertSame(['alpha', 'bravo'], $seen);
        $this->assertSame([true, true], $cleanupStates);
        $this->assertNull(TenantContext::tryCurrent());
    }

    public function testFailFastStopsAfterFirstTenantFailure(): void
    {
        $alphaId = $this->insertTenant('alpha', 'active');
        $this->insertTenant('bravo', 'active');
        $this->insertSchedule('tenant-fail-fast', [
            'tenant_scope' => PlatformScheduleRunner::SCOPE_ALL_ACTIVE_TENANTS,
            'options' => json_encode(['fail_fast' => true], JSON_THROW_ON_ERROR),
        ]);
        $dispatcher = new class implements PlatformScheduleDispatcherInterface {
            public function dispatch(array $schedule, ?TenantMetadata $tenant): void
            {
                throw new RuntimeException('token=super-secret failure');
            }
        };
        $runner = new PlatformScheduleRunner($dispatcher);

        $result = $runner->run('tenant-fail-fast');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['completed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['jobsCreated']);
        $jobs = $this->platform()
            ->execute('SELECT tenant_id, status, last_error FROM platform_jobs')
            ->fetchAll('assoc');
        $this->assertCount(1, $jobs);
        $this->assertSame($alphaId, $jobs[0]['tenant_id']);
        $this->assertSame('token=[redacted] failure', $jobs[0]['last_error']);
    }

    public function testAllActiveTenantsFailsClosedWhenNoActiveTenantsExist(): void
    {
        $this->insertTenant('suspended', 'suspended');
        $this->insertSchedule('tenant-sweep', [
            'tenant_scope' => PlatformScheduleRunner::SCOPE_ALL_ACTIVE_TENANTS,
        ]);
        $runner = new PlatformScheduleRunner($this->noopDispatcher());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active tenants are available');

        $runner->run('tenant-sweep');
    }

    public function testErrorScrubbingRedactsEmailAddresses(): void
    {
        $message = PlatformScheduleRunner::scrubError('Notify Admin.Person+test@example.org failed');

        $this->assertSame('Notify [redacted-email] failed', $message);
    }

    public function testRunDueDispatchesOnlySchedulesWhoseWindowHasArrived(): void
    {
        $this->insertSchedule('due-now', [
            'cron_expression' => '* * * * *',
            'next_run_at' => '2020-01-01 00:00:00',
        ]);
        $this->insertSchedule('future', [
            'cron_expression' => '* * * * *',
            'next_run_at' => '2099-01-01 00:00:00',
        ]);

        $result = (new PlatformScheduleRunner($this->noopDispatcher()))->runDue();

        $this->assertSame(1, $result['schedules']);
        $this->assertSame(1, $result['completed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['jobsCreated']);
        $job = $this->platform()->execute('SELECT parameters FROM platform_jobs')->fetch('assoc');
        $this->assertStringContainsString('due-now', (string)$job['parameters']);
    }

    public function testRunDueMarksInvalidCronAsFailedWithoutDispatching(): void
    {
        $this->insertSchedule('invalid-cron', [
            'cron_expression' => 'not a cron',
            'next_run_at' => '2020-01-01 00:00:00',
        ]);

        $result = (new PlatformScheduleRunner($this->noopDispatcher()))->runDue();

        $this->assertSame(0, $result['schedules']);
        $this->assertSame(1, $result['failed']);
        $schedule = $this->platform()->execute(
            'SELECT status, last_error FROM platform_schedules WHERE name = ?',
            ['invalid-cron'],
        )->fetch('assoc');
        $this->assertSame('failed', $schedule['status']);
        $this->assertSame('Invalid cron expression.', $schedule['last_error']);
    }

    private function createPlatformSchema(): void
    {
        $connection = $this->platform();
        $connection->execute(
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
        $connection->execute(
            'CREATE TABLE platform_schedules (
                id TEXT PRIMARY KEY,
                name TEXT UNIQUE NOT NULL,
                cron_expression TEXT NOT NULL,
                command TEXT NOT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                tenant_scope TEXT NOT NULL DEFAULT "platform",
                tenant_id TEXT NULL,
                payload TEXT NULL,
                options TEXT NULL,
                status TEXT NOT NULL DEFAULT "idle",
                last_run_at TEXT NULL,
                next_run_at TEXT NULL,
                last_success_at TEXT NULL,
                last_failure_at TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertSchedule(string $name, array $overrides = []): string
    {
        $id = Text::uuid();
        $data = array_merge([
            'id' => $id,
            'name' => $name,
            'cron_expression' => '* * * * *',
            'command' => 'platform:noop',
            'enabled' => 1,
            'tenant_scope' => PlatformScheduleRunner::SCOPE_PLATFORM,
            'tenant_id' => null,
            'payload' => json_encode(['secret' => 'not stored in jobs']),
            'options' => json_encode([]),
            'status' => 'idle',
            'created_at' => '2026-05-16 00:00:00',
            'modified_at' => null,
        ], $overrides);
        $this->platform()->insert('platform_schedules', $data);

        return $id;
    }

    private function insertTenant(string $slug, string $status): string
    {
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
            'schema_version' => null,
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

    private function noopDispatcher(): PlatformScheduleDispatcherInterface
    {
        return new class implements PlatformScheduleDispatcherInterface {
            public function dispatch(array $schedule, ?TenantMetadata $tenant): void
            {
            }
        };
    }

    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }
}
