<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupDeletionService;
use App\Services\Platform\Audit\WormAuditSinkInterface;
use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class BackupDeletionServiceTest extends TestCase
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
                    status TEXT NOT NULL,
                    object_uri TEXT NULL,
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

    public function testDeleteTenantRemovesObjectAndRetainsAuditedMetadata(): void
    {
        $backup = $this->insert('tenant_backups', 'tenant-backup', 'tenant-1', 'backup://tenants/acme/one');
        $storage = new DeletionTenantStorage();

        $this->service()->deleteTenant(
            $backup,
            $storage,
            'platform-user-1',
            'Remove superseded tenant recovery point.',
            ['tenant_slug' => 'acme'],
            ['ipAddress' => '127.0.0.1', 'userAgent' => 'phpunit'],
        );

        $this->assertSame(['backup://tenants/acme/one'], $storage->deleted);
        $deleted = $this->connection->execute(
            'SELECT status, object_uri, error_summary FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetch('assoc');
        $this->assertSame('deleted', $deleted['status']);
        $this->assertNull($deleted['object_uri']);
        $this->assertNull($deleted['error_summary']);
        $audit = $this->connection->execute(
            'SELECT tenant_id, action, subject_id, reason FROM audit_events ORDER BY id DESC LIMIT 1',
        )->fetch('assoc');
        $this->assertSame('tenant-1', $audit['tenant_id']);
        $this->assertSame('tenant_backup.deleted', $audit['action']);
        $this->assertSame('tenant-backup', $audit['subject_id']);
        $this->assertSame('Remove superseded tenant recovery point.', $audit['reason']);
        $actions = $this->connection->execute(
            'SELECT action FROM audit_events ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame([
            ['action' => 'tenant_backup.delete_requested'],
            ['action' => 'tenant_backup.deleted'],
        ], $actions);
    }

    public function testDeletePlatformRecordsPlatformAuditAction(): void
    {
        $backup = $this->insert(
            'platform_database_backups',
            'platform-backup',
            null,
            'backup://platform/one',
        );
        $storage = new DeletionTenantStorage();

        $this->service()->deletePlatform(
            $backup,
            $storage,
            'platform-user-1',
            'Remove superseded platform recovery point.',
        );

        $action = $this->connection->execute(
            'SELECT action FROM audit_events WHERE subject_id = ? ORDER BY id DESC LIMIT 1',
            ['platform-backup'],
        )->fetchColumn(0);
        $this->assertSame('platform_backup.deleted', $action);
    }

    public function testStorageFailureRestoresAvailabilityForRetry(): void
    {
        $backup = $this->insert('tenant_backups', 'tenant-backup', 'tenant-1', 'backup://tenants/acme/one');

        try {
            $this->service()->deleteTenant(
                $backup,
                new DeletionTenantStorage(true),
                'platform-user-1',
                'Test failed object deletion handling.',
            );
            $this->fail('Expected backup storage deletion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive could not be deleted from storage.', $exception->getMessage());
        }

        $retained = $this->connection->execute(
            'SELECT status, object_uri, error_summary FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetch('assoc');
        $this->assertSame('completed', $retained['status']);
        $this->assertSame('backup://tenants/acme/one', $retained['object_uri']);
        $this->assertNull($retained['error_summary']);
        $this->assertSame(
            2,
            (int)$this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0),
        );
    }

    public function testStorageFailurePreservesFailedBackupStateAndDiagnostic(): void
    {
        $backup = $this->insert(
            'tenant_backups',
            'tenant-backup',
            'tenant-1',
            'backup://tenants/acme/one',
            'failed',
            'Original backup failure.',
        );

        try {
            $this->service()->deleteTenant(
                $backup,
                new DeletionTenantStorage(true),
                'platform-user-1',
                'Test failed backup deletion handling.',
            );
            $this->fail('Expected backup storage deletion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive could not be deleted from storage.', $exception->getMessage());
        }

        $retained = $this->connection->execute(
            'SELECT status, error_summary FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetch('assoc');
        $this->assertSame('failed', $retained['status']);
        $this->assertSame('Original backup failure.', $retained['error_summary']);
    }

    public function testFailedRetryRemainsDeletingWhenPriorStateIsUnknown(): void
    {
        $backup = $this->insert(
            'tenant_backups',
            'tenant-backup',
            'tenant-1',
            'backup://tenants/acme/one',
            'deleting',
            'Original backup failure.',
        );

        try {
            $this->service()->deleteTenant(
                $backup,
                new DeletionTenantStorage(true),
                'platform-user-1',
                'Retry interrupted backup deletion.',
            );
            $this->fail('Expected backup storage deletion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive could not be deleted from storage.', $exception->getMessage());
        }

        $retained = $this->connection->execute(
            'SELECT status, error_summary FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetch('assoc');
        $this->assertSame('deleting', $retained['status']);
        $this->assertSame('Original backup failure.', $retained['error_summary']);
    }

    public function testActiveRestorePreventsArchiveDeletion(): void
    {
        $backup = $this->insert('tenant_backups', 'tenant-backup', 'tenant-1', 'backup://tenants/acme/one');
        $this->connection->insert('platform_jobs', [
            'id' => 'restore-job',
            'tenant_id' => 'restore-target-tenant',
            'job_type' => 'tenant_restore',
            'status' => 'queued',
            'parameters' => '{"backup_id":"tenant-backup"}',
        ]);
        $storage = new DeletionTenantStorage();

        try {
            $this->service()->deleteTenant(
                $backup,
                $storage,
                'platform-user-1',
                'Attempt unsafe active restore deletion.',
            );
            $this->fail('Expected active restore to block deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'A restore using this backup is already queued or running.',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], $storage->deleted);
        $status = $this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetchColumn(0);
        $this->assertSame('completed', $status);
    }

    public function testActiveRestoreLookupIsUnboundedAndCanonicalizesBackupId(): void
    {
        $backupId = '20f7fc3e-5fa8-49e7-9c84-9a5c9a856181';
        $backup = $this->insert(
            'tenant_backups',
            $backupId,
            'tenant-1',
            'backup://tenants/acme/one',
        );
        for ($index = 0; $index < 101; $index++) {
            $this->connection->insert('platform_jobs', [
                'id' => sprintf('unrelated-job-%03d', $index),
                'tenant_id' => 'restore-target-tenant',
                'job_type' => 'tenant_restore',
                'status' => 'queued',
                'parameters' => sprintf('{"backup_id":"unrelated-backup-%03d"}', $index),
            ]);
        }
        $this->connection->insert('platform_jobs', [
            'id' => 'matching-restore-job',
            'tenant_id' => 'restore-target-tenant',
            'job_type' => 'tenant_restore',
            'status' => 'running',
            'parameters' => '{"backup_id":"{20F7FC3E-5FA8-49E7-9C84-9A5C9A856181}"}',
        ]);
        $storage = new DeletionTenantStorage();

        try {
            $this->service()->deleteTenant(
                $backup,
                $storage,
                'platform-user-1',
                'Attempt unsafe active restore deletion.',
            );
            $this->fail('Expected active restore to block deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'A restore using this backup is already queued or running.',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], $storage->deleted);
    }

    public function testFailClosedIntentAuditPreventsStorageDeletion(): void
    {
        $backup = $this->insert('tenant_backups', 'tenant-backup', 'tenant-1', 'backup://tenants/acme/one');
        $storage = new DeletionTenantStorage();
        $sink = new class implements WormAuditSinkInterface {
            public function append(array $event): void
            {
                throw new RuntimeException('Immutable audit sink unavailable.');
            }
        };
        $service = new BackupDeletionService(
            $this->connection,
            new PlatformAuditService($this->connection, $sink, true),
        );

        try {
            $service->deleteTenant(
                $backup,
                $storage,
                'platform-user-1',
                'Test fail closed audit protection.',
            );
            $this->fail('Expected fail-closed auditing to block deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Backup deletion could not be authorized and audited.',
                $exception->getMessage(),
            );
        }

        $this->assertSame([], $storage->deleted);
        $status = $this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetchColumn(0);
        $this->assertSame('completed', $status);
        $this->assertSame(
            0,
            (int)$this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0),
        );
    }

    public function testFailureAuditCannotRollbackRecoveredBackupState(): void
    {
        $backup = $this->insert('tenant_backups', 'tenant-backup', 'tenant-1', 'backup://tenants/acme/one');
        $sink = new class implements WormAuditSinkInterface {
            private int $writes = 0;

            public function append(array $event): void
            {
                $this->writes++;
                if ($this->writes > 1) {
                    throw new RuntimeException('Immutable audit sink unavailable.');
                }
            }
        };
        $service = new BackupDeletionService(
            $this->connection,
            new PlatformAuditService($this->connection, $sink, true),
        );

        try {
            $service->deleteTenant(
                $backup,
                new DeletionTenantStorage(true),
                'platform-user-1',
                'Test failure audit state recovery.',
            );
            $this->fail('Expected backup storage deletion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Backup archive could not be deleted from storage.', $exception->getMessage());
        }

        $status = $this->connection->execute(
            'SELECT status FROM tenant_backups WHERE id = ?',
            ['tenant-backup'],
        )->fetchColumn(0);
        $this->assertSame('completed', $status);
        $actions = $this->connection->execute(
            'SELECT action FROM audit_events ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame([['action' => 'tenant_backup.delete_requested']], $actions);
    }

    private function service(): BackupDeletionService
    {
        return new BackupDeletionService($this->connection, new PlatformAuditService($this->connection));
    }

    /**
     * @return array<string, mixed>
     */
    private function insert(
        string $table,
        string $id,
        ?string $tenantId,
        string $objectUri,
        string $status = 'completed',
        ?string $errorSummary = null,
    ): array {
        $backup = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'status' => $status,
            'object_uri' => $objectUri,
            'error_summary' => $errorSummary,
            'modified_at' => null,
        ];
        $this->connection->insert($table, $backup);

        return $backup;
    }
}
