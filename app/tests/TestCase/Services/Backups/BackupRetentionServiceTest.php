<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupRetentionService;
use App\Services\Backups\TenantBackupService;
use App\Services\Platform\Audit\WormAuditSinkInterface;
use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class BackupRetentionServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        foreach (['tenant_backups', 'platform_database_backups'] as $table) {
            $this->connection->execute(sprintf(
                'CREATE TABLE %s (
                    id TEXT PRIMARY KEY,
                    tenant_id TEXT NULL,
                    backup_type TEXT NOT NULL,
                    status TEXT NOT NULL,
                    object_uri TEXT NULL,
                    retention_until TEXT NULL,
                    error_summary TEXT NULL,
                    modified_at TEXT NULL
                )',
                $table,
            ));
        }
        $this->connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                parameters TEXT NULL
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
                metadata TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                previous_hash TEXT NULL,
                event_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }

    public function testPruneDeletesExpiredObjectsAndRetainsMetadata(): void
    {
        $this->insert('tenant_backups', 'tenant-expired', 'tenant-1', 'backup://tenants/acme/expired');
        $this->insert(
            'tenant_backups',
            'tenant-current',
            'tenant-1',
            'backup://tenants/acme/current',
            '2099-01-01 00:00:00',
        );
        $this->insert('platform_database_backups', 'platform-expired', null, 'backup://platform/expired');
        $tenantStorage = new RetentionTenantStorage();
        $platformStorage = new RetentionPlatformStorage();
        $legacyStorage = new DeletionTenantStorage();

        $result = (new BackupRetentionService(
            $this->connection,
            $tenantStorage,
            $platformStorage,
            $legacyStorage,
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(['tenant_expired' => 1, 'platform_expired' => 1, 'failed' => 0], $result);
        $this->assertSame(['backup://tenants/acme/expired'], $tenantStorage->deleted);
        $this->assertSame(['backup://platform/expired'], $platformStorage->deleted);
        $expired = $this->connection->execute(
            'SELECT status, object_uri FROM tenant_backups WHERE id = ?',
            ['tenant-expired'],
        )->fetch('assoc');
        $this->assertSame('expired', $expired['status']);
        $this->assertNull($expired['object_uri']);
        $current = $this->connection->execute(
            'SELECT status, object_uri FROM tenant_backups WHERE id = ?',
            ['tenant-current'],
        )->fetch('assoc');
        $this->assertSame('completed', $current['status']);
        $this->assertSame('backup://tenants/acme/current', $current['object_uri']);
        $actions = $this->connection->execute(
            'SELECT action FROM audit_events ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame([
            ['action' => 'tenant_backup.expiration_requested'],
            ['action' => 'tenant_backup.expired'],
            ['action' => 'platform_backup.expiration_requested'],
            ['action' => 'platform_backup.expired'],
        ], $actions);
    }

    public function testPruneFailureKeepsObjectReferenceForRetry(): void
    {
        $this->insert('tenant_backups', 'tenant-expired', 'tenant-1', 'backup://tenants/acme/expired');
        $tenantStorage = new RetentionTenantStorage(true);

        $result = (new BackupRetentionService(
            $this->connection,
            $tenantStorage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(1, $result['failed']);
        $backup = $this->connection->execute(
            'SELECT status, object_uri, error_summary FROM tenant_backups WHERE id = ?',
            ['tenant-expired'],
        )->fetch('assoc');
        $this->assertSame('completed', $backup['status']);
        $this->assertSame('backup://tenants/acme/expired', $backup['object_uri']);
        $this->assertStringContainsString('Retention cleanup failed', (string)$backup['error_summary']);
        $this->assertStringNotContainsString('secret-token', (string)$backup['error_summary']);
    }

    public function testPruneRejectsArchiveUsedByActiveRestore(): void
    {
        $this->insert('tenant_backups', 'tenant-expired', 'tenant-1', 'backup://tenants/acme/expired');
        $this->connection->insert('platform_jobs', [
            'id' => 'restore-job',
            'tenant_id' => 'restore-target-tenant',
            'job_type' => 'tenant_restore',
            'status' => 'queued',
            'parameters' => '{"backup_id":"tenant-expired"}',
        ]);
        $tenantStorage = new RetentionTenantStorage();

        $result = (new BackupRetentionService(
            $this->connection,
            $tenantStorage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(['tenant_expired' => 0, 'platform_expired' => 0, 'failed' => 1], $result);
        $this->assertSame([], $tenantStorage->deleted);
        $status = $this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            ['tenant-expired'],
        )->fetchColumn(0);
        $this->assertSame('completed', $status);
    }

    public function testPruneRoutesLegacyAndManagedArchiveStorage(): void
    {
        $this->insert('tenant_backups', 'current', 'tenant-1', 'backup://tenants/acme/current');
        $this->insert('tenant_backups', 'historical', 'tenant-1', 'local://tenants/acme/historical');
        $this->insert(
            'tenant_backups',
            'legacy',
            'tenant-1',
            'tenant-1/legacy.kmpbackup',
            backupType: TenantBackupService::LEGACY_BACKUP_TYPE,
        );
        $managedStorage = new RetentionTenantStorage();
        $legacyStorage = new DeletionTenantStorage();

        $result = (new BackupRetentionService(
            $this->connection,
            $managedStorage,
            new RetentionPlatformStorage(),
            $legacyStorage,
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(3, $result['tenant_expired']);
        $this->assertSame([
            'backup://tenants/acme/current',
            'local://tenants/acme/historical',
        ], $managedStorage->deleted);
        $this->assertSame(['tenant-1/legacy.kmpbackup'], $legacyStorage->deleted);
    }

    public function testPruneFailsClosedWhenIntentAuditCannotBePersisted(): void
    {
        $this->insert('tenant_backups', 'tenant-expired', 'tenant-1', 'backup://tenants/acme/expired');
        $storage = new RetentionTenantStorage();
        $sink = new class implements WormAuditSinkInterface {
            public function append(array $event): void
            {
                throw new RuntimeException('Immutable audit sink unavailable.');
            }
        };

        $result = (new BackupRetentionService(
            $this->connection,
            $storage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection, $sink, true),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(1, $result['failed']);
        $this->assertSame([], $storage->deleted);
        $status = $this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            ['tenant-expired'],
        )->fetchColumn(0);
        $this->assertSame('completed', $status);
        $this->assertSame(
            0,
            (int)$this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0),
        );
    }

    public function testPruneRetriesInterruptedExpirationFinalization(): void
    {
        $this->insert('tenant_backups', 'tenant-expired', 'tenant-1', 'backup://tenants/acme/expired');
        $storage = new RetentionTenantStorage();
        $sink = new class implements WormAuditSinkInterface {
            private int $writes = 0;

            public function append(array $event): void
            {
                $this->writes++;
                if ($this->writes === 2) {
                    throw new RuntimeException('Immutable audit sink unavailable.');
                }
            }
        };

        $firstResult = (new BackupRetentionService(
            $this->connection,
            $storage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection, $sink, true),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(1, $firstResult['failed']);
        $this->assertSame('expiring', $this->backupStatus('tenant-expired'));
        $this->assertSame(['backup://tenants/acme/expired'], $storage->deleted);

        $secondResult = (new BackupRetentionService(
            $this->connection,
            $storage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(1, $secondResult['tenant_expired']);
        $this->assertSame('expired', $this->backupStatus('tenant-expired'));
        $this->assertSame([
            'backup://tenants/acme/expired',
            'backup://tenants/acme/expired',
        ], $storage->deleted);
    }

    public function testConcurrentCompletedExpirationIsNotReportedAsFailure(): void
    {
        $this->insert(
            'tenant_backups',
            'first-expired',
            'tenant-1',
            'backup://tenants/acme/first',
            '2026-06-01 00:00:00',
        );
        $this->insert(
            'tenant_backups',
            'concurrently-expired',
            'tenant-1',
            'backup://tenants/acme/concurrent',
            '2026-07-01 00:00:00',
        );
        $storage = new RetentionTenantStorage(false, function (string $objectUri): void {
            if ($objectUri !== 'backup://tenants/acme/first') {
                return;
            }
            $this->connection->update('tenant_backups', [
                'status' => 'expired',
                'object_uri' => null,
                'error_summary' => null,
            ], ['id' => 'concurrently-expired']);
        });

        $result = (new BackupRetentionService(
            $this->connection,
            $storage,
            new RetentionPlatformStorage(),
            new DeletionTenantStorage(),
            new PlatformAuditService($this->connection),
        ))->prune('2026-07-10 12:00:00');

        $this->assertSame(['tenant_expired' => 1, 'platform_expired' => 0, 'failed' => 0], $result);
        $raced = $this->connection->execute(
            'SELECT status, object_uri, error_summary FROM tenant_backups WHERE id = ?',
            ['concurrently-expired'],
        )->fetch('assoc');
        $this->assertSame('expired', $raced['status']);
        $this->assertNull($raced['object_uri']);
        $this->assertNull($raced['error_summary']);
    }

    private function insert(
        string $table,
        string $id,
        ?string $tenantId,
        string $objectUri,
        string $retentionUntil = '2026-07-01 00:00:00',
        string $backupType = 'json',
    ): void {
        $this->connection->insert($table, [
            'id' => $id,
            'tenant_id' => $tenantId,
            'backup_type' => $backupType,
            'status' => 'completed',
            'object_uri' => $objectUri,
            'retention_until' => $retentionUntil,
            'error_summary' => null,
            'modified_at' => null,
        ]);
    }

    private function backupStatus(string $backupId): string
    {
        return (string)$this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            [$backupId],
        )->fetchColumn(0);
    }
}
