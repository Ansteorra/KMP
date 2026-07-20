<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\TenantBackupService;
use App\Services\Backups\TenantSelfServiceBackupService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use RuntimeException;

class TenantSelfServiceBackupServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                object_uri TEXT NULL,
                object_size_bytes INTEGER NULL,
                object_sha256 TEXT NULL,
                encryption_algorithm TEXT NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NULL,
                wrapped_dek_key_version TEXT NULL,
                wrapped_dek_metadata TEXT NULL,
                error_summary TEXT NULL,
                retention_until TEXT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                recovery_key_exported_at TEXT NULL,
                recovery_key_exported_by TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->connection->execute(
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
        $this->connection->execute(
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
        $this->connection->execute(
            'CREATE TABLE platform_settings (
                key TEXT PRIMARY KEY,
                value TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->connection->insert('tenants', ['id' => 'tenant-1', 'slug' => 'demo', 'status' => 'active']);
        $this->connection->insert('tenants', ['id' => 'tenant-2', 'slug' => 'other', 'status' => 'active']);
    }

    private function service(): TenantSelfServiceBackupService
    {
        return new TenantSelfServiceBackupService($this->connection);
    }

    private function insertBackup(string $id, string $tenantId, array $overrides = []): void
    {
        $this->connection->insert('tenant_backups', $overrides + [
            'id' => $id,
            'tenant_id' => $tenantId,
            'backup_type' => 'json',
            'status' => 'completed',
            'object_uri' => 'backup://tenants/demo/' . $id . '.json.gz.enc',
            'created_at' => '2026-07-10 00:00:00',
            'completed_at' => '2026-07-10 00:05:00',
        ]);
    }

    public function testListManagedBackupsIsScopedToTenant(): void
    {
        $this->insertBackup('backup-1', 'tenant-1');
        $this->insertBackup('backup-2', 'tenant-2');

        $rows = $this->service()->listManagedBackups('tenant-1');

        $this->assertCount(1, $rows);
        $this->assertSame('backup-1', $rows[0]['id']);
    }

    public function testStatusReportsPolicyAndStaleness(): void
    {
        $fresh = DateTime::now('UTC')->subHours(2)->format('Y-m-d H:i:s');
        $this->insertBackup('backup-1', 'tenant-1', ['completed_at' => $fresh]);

        $status = $this->service()->status('tenant-1');

        $this->assertSame('daily', $status['cadence']);
        $this->assertSame(30, $status['retention_days']);
        $this->assertFalse($status['stale']);
        $this->assertSame('backup-1', $status['latest_completed']['id']);

        $statusOther = $this->service()->status('tenant-2');
        $this->assertTrue($statusOther['stale']);
        $this->assertNull($statusOther['latest_completed']);
    }

    public function testRequestBackupQueuesManagedJob(): void
    {
        $job = $this->service()->requestBackup('tenant-1', 'demo', 'member-9');

        $this->assertSame(TenantBackupService::JOB_TYPE, $job['job_type']);
        $this->assertSame('queued', $job['status']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame('demo', $parameters['tenant_slug']);
        $this->assertSame(30, $parameters['retention_days']);
        $this->assertSame('tenant', $parameters['initiator']);
        $this->assertSame('member-9', $parameters['requested_by_member']);
    }

    public function testRequestBackupRejectsMismatchedSlug(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant was not found.');
        $this->service()->requestBackup('tenant-1', 'other', 'member-9');
    }

    public function testRequestBackupRejectsInactiveTenant(): void
    {
        $this->connection->update('tenants', ['status' => 'suspended'], ['id' => 'tenant-1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backups can only be requested while the tenant is active.');
        $this->service()->requestBackup('tenant-1', 'demo', 'member-9');
    }

    public function testRequestBackupIsRateLimitedByRecentCompletion(): void
    {
        $recent = DateTime::now('UTC')->subMinutes(10)->format('Y-m-d H:i:s');
        $this->insertBackup('backup-1', 'tenant-1', ['completed_at' => $recent]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Try again later.');
        $this->service()->requestBackup('tenant-1', 'demo', 'member-9');
    }

    public function testGetBackupForDownloadAssertsOwnership(): void
    {
        $this->insertBackup('backup-2', 'tenant-2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup was not found.');
        $this->service()->getBackupForDownload('tenant-1', 'backup-2');
    }

    public function testClaimRecoveryKeyExportIsOneTime(): void
    {
        $this->insertBackup('backup-1', 'tenant-1');
        $service = $this->service();

        $this->assertTrue($service->claimRecoveryKeyExport('backup-1', 'member:9'));
        $this->assertFalse($service->claimRecoveryKeyExport('backup-1', 'member:10'));

        $row = $this->connection->execute(
            'SELECT recovery_key_exported_by FROM tenant_backups WHERE id = :id',
            ['id' => 'backup-1'],
        )->fetch('assoc');
        $this->assertSame('member:9', $row['recovery_key_exported_by']);
    }
}
