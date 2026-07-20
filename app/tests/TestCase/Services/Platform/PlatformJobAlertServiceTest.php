<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformJobAlertService;
use App\Services\Platform\PlatformScheduleRunner;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;

class PlatformJobAlertServiceTest extends TestCase
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

    public function testDetectsStaleRunningJob(): void
    {
        $this->insertJob([
            'job_type' => 'tenant_backup',
            'status' => 'running',
            'started_at' => '2026-05-16 10:00:00',
            'created_at' => '2026-05-16 10:00:00',
        ]);

        $result = $this->service()->check();

        $this->assertFalse($result['healthy']);
        $this->assertSame(PlatformJobAlertService::TYPE_STALE_RUNNING_JOB, $result['alerts'][0]['type']);
        $this->assertSame('tenant_backup', $result['alerts'][0]['job_type']);
        $this->assertSame(120, $result['alerts'][0]['age_minutes']);
    }

    public function testDetectsMissingScheduleSuccess(): void
    {
        $this->insertSchedule('nightly-sweep', [
            'last_success_at' => '2026-05-14 12:00:00',
            'options' => json_encode(['max_age_warning_minutes' => 60]),
        ]);

        $result = $this->service()->check();

        $this->assertFalse($result['healthy']);
        $this->assertSame(PlatformJobAlertService::TYPE_MISSING_SCHEDULE_SUCCESS, $result['alerts'][0]['type']);
        $this->assertSame('nightly-sweep', $result['alerts'][0]['schedule_name']);
        $this->assertSame(60, $result['alerts'][0]['threshold_minutes']);
    }

    public function testDetectsFailureThreshold(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->insertJob([
                'job_type' => 'platform_schedule',
                'status' => 'failed',
                'last_error' => 'attempt failed',
                'finished_at' => sprintf('2026-05-16 11:5%d:00', $i),
                'created_at' => sprintf('2026-05-16 11:5%d:00', $i),
            ]);
        }

        $result = $this->service()->check();

        $this->assertFalse($result['healthy']);
        $this->assertSame(PlatformJobAlertService::TYPE_REPEATED_FAILURES, $result['alerts'][0]['type']);
        $this->assertSame(3, $result['alerts'][0]['failure_count']);
        $this->assertSame('attempt failed', $result['alerts'][0]['last_error']);
    }

    public function testHealthyStateReturnsNoAlerts(): void
    {
        $this->insertSchedule('healthy-sweep', [
            'last_success_at' => '2026-05-16 11:55:00',
        ]);
        $this->insertJob([
            'job_type' => 'platform_schedule',
            'status' => 'completed',
            'created_at' => '2026-05-16 11:55:00',
            'finished_at' => '2026-05-16 11:56:00',
        ]);

        $result = $this->service()->check();

        $this->assertTrue($result['healthy']);
        $this->assertSame([], $result['alerts']);
    }

    public function testSensitiveErrorTextIsScrubbedInAlerts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->insertJob([
                'job_type' => 'platform_schedule',
                'status' => 'failed',
                'last_error' => 'password=SuperSecret token:abc123 user admin@example.test Bearer abc.def',
                'finished_at' => sprintf('2026-05-16 11:4%d:00', $i),
                'created_at' => sprintf('2026-05-16 11:4%d:00', $i),
            ]);
        }

        $result = $this->service()->check();
        $encoded = json_encode($result) ?: '';

        $this->assertStringContainsString('password=[redacted]', $encoded);
        $this->assertStringContainsString('token=[redacted]', $encoded);
        $this->assertStringContainsString('[redacted-email]', $encoded);
        $this->assertStringContainsString('Bearer [redacted]', $encoded);
        $this->assertStringNotContainsString('SuperSecret', $encoded);
        $this->assertStringNotContainsString('abc123', $encoded);
        $this->assertStringNotContainsString('admin@example.test', $encoded);
        $this->assertStringNotContainsString('abc.def', $encoded);
    }

    private function service(): PlatformJobAlertService
    {
        return new PlatformJobAlertService(60, 1440, 3, 60, new DateTime('2026-05-16 12:00:00'));
    }

    private function createPlatformSchema(): void
    {
        $connection = $this->platform();
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
     * @param array<string, mixed> $overrides Schedule column overrides
     */
    private function insertSchedule(string $name, array $overrides = []): void
    {
        $this->platform()->insert('platform_schedules', array_merge([
            'id' => Text::uuid(),
            'name' => $name,
            'cron_expression' => '* * * * *',
            'command' => 'platform:noop',
            'enabled' => 1,
            'tenant_scope' => PlatformScheduleRunner::SCOPE_PLATFORM,
            'tenant_id' => null,
            'payload' => null,
            'options' => json_encode([]),
            'status' => 'idle',
            'last_run_at' => null,
            'next_run_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'last_error' => null,
            'created_at' => '2026-05-16 00:00:00',
            'modified_at' => null,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides Job column overrides
     */
    private function insertJob(array $overrides): void
    {
        $this->platform()->insert('platform_jobs', array_merge([
            'id' => Text::uuid(),
            'tenant_id' => null,
            'requested_by_platform_user_id' => null,
            'job_type' => 'platform_schedule',
            'status' => 'completed',
            'idempotency_key' => null,
            'parameters' => null,
            'log_uri' => null,
            'last_error' => null,
            'created_at' => '2026-05-16 12:00:00',
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => null,
        ], $overrides));
    }

    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }
}
