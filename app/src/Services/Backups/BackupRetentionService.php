<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformScheduleRunner;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use DateTimeInterface;
use Throwable;

/**
 * Deletes expired encrypted objects while retaining non-sensitive metadata.
 */
final class BackupRetentionService
{
    private readonly BackupDeletionService $deletion;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $platform,
        private readonly TenantBackupStorageInterface $tenantStorage,
        private readonly PlatformDatabaseBackupStorageInterface $platformStorage,
        private readonly BackupArchiveStorageInterface $legacyStorage,
        PlatformAuditService $audit,
    ) {
        $this->deletion = new BackupDeletionService($platform, $audit);
    }

    /**
     * @return array{tenant_expired: int, platform_expired: int, failed: int}
     */
    public function prune(DateTimeInterface|string|null $now = null, int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $timestamp = $this->timestamp($now);
        $result = ['tenant_expired' => 0, 'platform_expired' => 0, 'failed' => 0];

        foreach ($this->expiredTenantBackups($timestamp, $limit) as $backup) {
            try {
                $this->deletion->expireTenant(
                    $backup,
                    $this->storageFor($backup, $this->tenantStorage),
                    'backup retention policy expired object',
                    ['retention_until' => $backup['retention_until'] ?? null],
                    ['userAgent' => 'platform-scheduler'],
                );
                $result['tenant_expired']++;
            } catch (Throwable $exception) {
                if ($this->archiveRemovalCompletedConcurrently('tenant_backups', $backup, $exception)) {
                    continue;
                }
                $this->markRetentionFailure('tenant_backups', $backup, $exception, $timestamp);
                $result['failed']++;
            }
        }

        foreach ($this->expiredPlatformBackups($timestamp, $limit) as $backup) {
            try {
                $this->deletion->expirePlatform(
                    $backup,
                    $this->storageFor($backup, $this->platformStorage),
                    'backup retention policy expired object',
                    ['retention_until' => $backup['retention_until'] ?? null],
                    ['userAgent' => 'platform-scheduler'],
                );
                $result['platform_expired']++;
            } catch (Throwable $exception) {
                if ($this->archiveRemovalCompletedConcurrently('platform_database_backups', $backup, $exception)) {
                    continue;
                }
                $this->markRetentionFailure('platform_database_backups', $backup, $exception, $timestamp);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expiredTenantBackups(string $now, int $limit): array
    {
        return $this->platform->execute(
            'SELECT id, tenant_id, backup_type, status, object_uri, error_summary, retention_until
               FROM tenant_backups
              WHERE status IN (:completed, :expiring)
                AND object_uri IS NOT NULL
                AND retention_until IS NOT NULL
                AND retention_until <= :now
           ORDER BY retention_until ASC
              LIMIT :limit',
            ['completed' => 'completed', 'expiring' => 'expiring', 'now' => $now, 'limit' => $limit],
            ['limit' => 'integer'],
        )->fetchAll('assoc');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expiredPlatformBackups(string $now, int $limit): array
    {
        return $this->platform->execute(
            'SELECT id, backup_type, status, object_uri, error_summary, retention_until
               FROM platform_database_backups
              WHERE status IN (:completed, :expiring)
                AND object_uri IS NOT NULL
                AND retention_until IS NOT NULL
                AND retention_until <= :now
           ORDER BY retention_until ASC
              LIMIT :limit',
            ['completed' => 'completed', 'expiring' => 'expiring', 'now' => $now, 'limit' => $limit],
            ['limit' => 'integer'],
        )->fetchAll('assoc');
    }

    /**
     * Persist a sanitized cleanup failure for a later retry.
     *
     * @param array<string, mixed> $backup
     */
    private function markRetentionFailure(
        string $table,
        array $backup,
        Throwable $exception,
        string $now,
    ): void {
        $this->platform->update($table, [
            'error_summary' => mb_substr(
                'Retention cleanup failed: ' . PlatformScheduleRunner::scrubError($exception->getMessage()),
                0,
                2000,
            ),
            'modified_at' => $now,
        ], [
            'id' => (string)$backup['id'],
            'object_uri' => (string)$backup['object_uri'],
        ]);
    }

    /**
     * Treat another process completing removal after candidate selection as success.
     *
     * @param array<string, mixed> $backup
     */
    private function archiveRemovalCompletedConcurrently(
        string $table,
        array $backup,
        Throwable $exception,
    ): bool {
        if ($exception->getMessage() !== 'Backup archive is no longer available for deletion.') {
            return false;
        }
        $row = $this->platform->execute(
            sprintf('SELECT status, object_uri FROM %s WHERE id = :id LIMIT 1', $table),
            ['id' => (string)$backup['id']],
        )->fetch('assoc');

        return is_array($row)
            && in_array((string)$row['status'], ['deleted', 'expired'], true)
            && $row['object_uri'] === null;
    }

    /**
     * @param array<string, mixed> $backup
     */
    private function storageFor(
        array $backup,
        BackupArchiveStorageInterface $defaultStorage,
    ): BackupArchiveStorageInterface {
        if ((string)($backup['backup_type'] ?? '') === TenantBackupService::LEGACY_BACKUP_TYPE) {
            return $this->legacyStorage;
        }

        return $defaultStorage;
    }

    /**
     * Normalize the retention cutoff to UTC database format.
     */
    private function timestamp(DateTimeInterface|string|null $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && trim($value) !== '') {
            return (new DateTime($value))->format('Y-m-d H:i:s');
        }

        return DateTime::now('UTC')->format('Y-m-d H:i:s');
    }
}
