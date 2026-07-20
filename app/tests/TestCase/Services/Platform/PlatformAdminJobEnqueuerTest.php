<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PlatformAdminJobEnqueuerTest extends TestCase
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
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL
            )',
        );
        $this->connection->insert('tenants', [
            'id' => 'tenant-1',
            'status' => 'suspended',
        ]);
        $this->connection->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL
            ,
                recovery_key_exported_at TEXT NULL,
                recovery_key_exported_by TEXT NULL
            )',
        );
        $this->connection->insert('tenant_backups', [
            'id' => 'backup-1',
            'tenant_id' => 'tenant-1',
            'backup_type' => 'json',
            'status' => 'completed',
        ]);
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
    }

    public function testEnqueuePersistsJobAndAuditEvent(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $job = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant', 'tenant_slug' => 'example'],
            'tenant_backup:example:nonce',
            'manual backup request',
            ['ipAddress' => '127.0.0.1', 'userAgent' => 'test'],
        );

        $this->assertSame('tenant_backup', $job['job_type']);
        $this->assertSame('queued', $job['status']);
        $storedJob = $this->connection->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame($job['id'], $storedJob['id']);
        $this->assertSame('tenant_backup:example:nonce', $storedJob['idempotency_key']);

        $audit = $this->connection->execute('SELECT * FROM audit_events')->fetch('assoc');
        $this->assertSame('platform_job.queued', $audit['action']);
        $this->assertSame($job['id'], $audit['subject_id']);
        $this->assertStringContainsString('tenant_backup:example:nonce', $audit['metadata']);
    }

    public function testEnqueueReturnsExistingJobForDuplicateIdempotencyKey(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $first = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant'],
            'duplicate-key',
            'manual backup request',
        );
        $second = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant', 'changed' => true],
            'duplicate-key',
            'manual backup request',
        );

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, (int)$this->connection->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0));
        $this->assertSame(1, (int)$this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0));
    }

    public function testRejectsOverlappingTenantLifecycleOperations(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );
        $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example'],
            'first-backup',
            'manual backup request',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another lifecycle operation is already queued or running');

        $service->enqueue(
            'tenant_restore',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example', 'backup_id' => 'backup-1'],
            'overlapping-restore',
            'manual restore request',
        );
    }

    public function testAllowsNewTenantOperationAfterPreviousJobCompleted(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );
        $first = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example'],
            'completed-backup',
            'manual backup request',
        );
        $this->connection->update('platform_jobs', ['status' => 'completed'], ['id' => $first['id']]);

        $restore = $service->enqueue(
            'tenant_restore',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example', 'backup_id' => 'backup-1'],
            'later-restore',
            'manual restore request',
        );

        $this->assertSame('queued', $restore['status']);
        $this->assertSame(2, (int)$this->connection->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0));
    }

    public function testRejectsOperationMatchingBootstrapJobSlug(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );
        $service->enqueue(
            'tenant_provision',
            null,
            'platform-user-1',
            ['tenant_slug' => 'example'],
            'bootstrap-provision',
            'tenant bootstrap',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another lifecycle operation is already queued or running');

        $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example'],
            'bootstrap-overlap',
            'manual backup request',
        );
    }

    public function testBootstrapConflictLookupIsNotCapped(): void
    {
        for ($index = 0; $index < 101; $index++) {
            $this->insertProvisionJob($index, sprintf('unrelated-%03d', $index));
        }
        $this->insertProvisionJob(101, 'example');
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another lifecycle operation is already queued or running');

        $service->enqueue(
            'tenant_provision',
            null,
            'platform-user-1',
            ['tenant_slug' => 'example'],
            'duplicate-bootstrap',
            'tenant bootstrap',
        );
    }

    public function testRestoreRechecksBackupAvailabilityBeforeEnqueue(): void
    {
        $this->connection->update('tenant_backups', ['status' => 'deleting'], ['id' => 'backup-1']);
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant backup is no longer available for restore.');

        $service->enqueue(
            'tenant_restore',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example', 'backup_id' => 'backup-1'],
            'deleting-backup-restore',
            'manual restore request',
        );
    }

    public function testRestoreRequiresTenantToRemainSuspended(): void
    {
        $this->connection->update('tenants', ['status' => 'active'], ['id' => 'tenant-1']);
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant must remain suspended before a restore can be queued.');

        $service->enqueue(
            'tenant_restore',
            'tenant-1',
            'platform-user-1',
            ['tenant_slug' => 'example', 'backup_id' => 'backup-1'],
            'active-tenant-restore',
            'manual restore request',
        );
    }

    public function testRejectsConcurrentPlatformDatabaseBackups(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );
        $service->enqueue(
            'platform_database_backup',
            null,
            'platform-user-1',
            ['retention_days' => 30],
            'platform-backup-1',
            'manual platform backup',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already queued or running');

        $service->enqueue(
            'platform_database_backup',
            null,
            'platform-user-1',
            ['retention_days' => 30],
            'platform-backup-2',
            'manual platform backup',
        );
    }

    private function insertProvisionJob(int $index, string $tenantSlug): void
    {
        $now = '2026-07-10 12:00:00';
        $this->connection->insert('platform_jobs', [
            'id' => sprintf('provision-job-%03d', $index),
            'tenant_id' => null,
            'requested_by_platform_user_id' => null,
            'job_type' => 'tenant_provision',
            'status' => 'queued',
            'idempotency_key' => sprintf('provision-%03d', $index),
            'parameters' => json_encode(['tenant_slug' => $tenantSlug]),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }
}
