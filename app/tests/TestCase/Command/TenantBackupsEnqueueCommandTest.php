<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

class TenantBackupsEnqueueCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

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
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testEnqueuesBackupsForDueActiveTenantsOnly(): void
    {
        $platform = $this->platform();
        $platform->insert('tenants', ['id' => 'tenant-due', 'slug' => 'due', 'status' => 'active']);
        $platform->insert('tenants', ['id' => 'tenant-fresh', 'slug' => 'fresh', 'status' => 'active']);
        $platform->insert('tenants', ['id' => 'tenant-off', 'slug' => 'off', 'status' => 'suspended']);
        $platform->insert('tenant_backups', [
            'id' => 'backup-fresh',
            'tenant_id' => 'tenant-fresh',
            'backup_type' => 'json',
            'status' => 'completed',
            'created_at' => DateTime::now('UTC')->subHours(2)->format('Y-m-d H:i:s'),
            'completed_at' => DateTime::now('UTC')->subHours(2)->format('Y-m-d H:i:s'),
        ]);

        $this->exec('tenant_backups_enqueue');

        $this->assertExitSuccess();
        $this->assertOutputContains('1 enqueued, 1 fresh');
        $jobs = $platform->execute('SELECT * FROM platform_jobs')->fetchAll('assoc');
        $this->assertCount(1, $jobs);
        $this->assertSame('tenant-due', $jobs[0]['tenant_id']);
        $this->assertSame('tenant_backup', $jobs[0]['job_type']);
        $parameters = json_decode((string)$jobs[0]['parameters'], true);
        $this->assertSame('due', $parameters['tenant_slug']);
        $this->assertSame(30, $parameters['retention_days']);
        $this->assertSame('schedule', $parameters['initiator']);
    }

    public function testRerunOnTheSameDayIsIdempotent(): void
    {
        $platform = $this->platform();
        $platform->insert('tenants', ['id' => 'tenant-due', 'slug' => 'due', 'status' => 'active']);

        $this->exec('tenant_backups_enqueue');
        $this->assertExitSuccess();
        $this->exec('tenant_backups_enqueue');
        $this->assertExitSuccess();

        $count = (int)$this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0);
        $this->assertSame(1, $count);
    }

    public function testConflictingLifecycleOperationDoesNotFailTheSweep(): void
    {
        $platform = $this->platform();
        $platform->insert('tenants', ['id' => 'tenant-busy', 'slug' => 'busy', 'status' => 'active']);
        $platform->insert('platform_jobs', [
            'id' => 'job-existing',
            'tenant_id' => 'tenant-busy',
            'job_type' => 'tenant_restore',
            'status' => 'running',
            'idempotency_key' => 'existing',
            'parameters' => '{"tenant_slug":"busy"}',
            'created_at' => '2026-07-10 00:00:00',
        ]);

        $this->exec('tenant_backups_enqueue');

        $this->assertExitSuccess();
        $this->assertOutputContains('1 skipped (operation in flight)');
        $count = (int)$platform->execute(
            "SELECT COUNT(*) FROM platform_jobs WHERE job_type = 'tenant_backup'",
        )->fetchColumn(0);
        $this->assertSame(0, $count);
    }

    public function testWeeklyPolicySkipsBackupsYoungerThanAWeek(): void
    {
        $platform = $this->platform();
        $platform->insert('platform_settings', ['key' => 'backup.cadence', 'value' => 'weekly']);
        $platform->insert('tenants', ['id' => 'tenant-1', 'slug' => 'demo', 'status' => 'active']);
        $platform->insert('tenant_backups', [
            'id' => 'backup-1',
            'tenant_id' => 'tenant-1',
            'backup_type' => 'json',
            'status' => 'completed',
            'created_at' => DateTime::now('UTC')->subDays(3)->format('Y-m-d H:i:s'),
            'completed_at' => DateTime::now('UTC')->subDays(3)->format('Y-m-d H:i:s'),
        ]);

        $this->exec('tenant_backups_enqueue');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 enqueued, 1 fresh');
    }

    private function createSchema(): void
    {
        $platform = $this->platform();
        $platform->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $platform->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                recovery_key_exported_at TEXT NULL,
                recovery_key_exported_by TEXT NULL
            )',
        );
        $platform->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL UNIQUE,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $platform->execute(
            'CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT NULL,
                platform_user_id TEXT NULL,
                action TEXT NOT NULL,
                subject_type TEXT NULL,
                subject_id TEXT NULL,
                reason TEXT NULL,
                metadata TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NOT NULL,
                previous_hash TEXT NULL,
                event_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $platform->execute(
            'CREATE TABLE platform_settings (
                key TEXT PRIMARY KEY,
                value TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }
}
