<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use RuntimeException;
use Throwable;

/**
 * Removes managed backup objects while retaining immutable operational metadata.
 */
final class BackupDeletionService
{
    private const DELETE_OPERATION = [
        'intent_action' => 'delete_requested',
        'failure_action' => 'delete_failed',
        'success_action' => 'deleted',
        'final_status' => 'deleted',
        'clear_error' => false,
        'reservation_status' => 'deleting',
    ];

    private const EXPIRE_OPERATION = [
        'intent_action' => 'expiration_requested',
        'failure_action' => 'expiration_failed',
        'success_action' => 'expired',
        'final_status' => 'expired',
        'clear_error' => true,
        'reservation_status' => 'expiring',
    ];

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $platform,
        private readonly PlatformAuditService $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     */
    public function deleteTenant(
        array $backup,
        BackupArchiveStorageInterface $storage,
        ?string $platformUserId,
        string $reason,
        array $metadata = [],
        array $auditOptions = [],
    ): void {
        $tenantId = trim((string)($backup['tenant_id'] ?? ''));
        if ($tenantId === '') {
            throw new RuntimeException('Tenant backup metadata is incomplete.');
        }

        $auditOptions['tenantId'] = $tenantId;
        $this->delete(
            $backup,
            $storage,
            'tenant_backups',
            'tenant_backup',
            'tenant_backup',
            $tenantId,
            $platformUserId,
            $reason,
            $metadata,
            $auditOptions,
            self::DELETE_OPERATION,
        );
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     */
    public function deletePlatform(
        array $backup,
        BackupArchiveStorageInterface $storage,
        ?string $platformUserId,
        string $reason,
        array $metadata = [],
        array $auditOptions = [],
    ): void {
        $this->delete(
            $backup,
            $storage,
            'platform_database_backups',
            'platform_backup',
            'platform_database_backup',
            null,
            $platformUserId,
            $reason,
            $metadata,
            $auditOptions,
            self::DELETE_OPERATION,
        );
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     */
    public function expireTenant(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $reason,
        array $metadata = [],
        array $auditOptions = [],
    ): void {
        $tenantId = trim((string)($backup['tenant_id'] ?? ''));
        if ($tenantId === '') {
            throw new RuntimeException('Tenant backup metadata is incomplete.');
        }

        $auditOptions['tenantId'] = $tenantId;
        $this->delete(
            $backup,
            $storage,
            'tenant_backups',
            'tenant_backup',
            'backup',
            $tenantId,
            null,
            $reason,
            $metadata,
            $auditOptions,
            self::EXPIRE_OPERATION,
        );
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     */
    public function expirePlatform(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $reason,
        array $metadata = [],
        array $auditOptions = [],
    ): void {
        $this->delete(
            $backup,
            $storage,
            'platform_database_backups',
            'platform_backup',
            'backup',
            null,
            null,
            $reason,
            $metadata,
            $auditOptions,
            self::EXPIRE_OPERATION,
        );
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     * @param array{
     *   intent_action: string,
     *   failure_action: string,
     *   success_action: string,
     *   final_status: string,
     *   clear_error: bool,
     *   reservation_status: string
     * } $operation
     */
    private function delete(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $table,
        string $auditScope,
        string $subjectType,
        ?string $tenantId,
        ?string $platformUserId,
        string $reason,
        array $metadata,
        array $auditOptions,
        array $operation,
    ): void {
        $backupId = trim((string)($backup['id'] ?? ''));
        if ($backupId === '') {
            throw new RuntimeException('Backup archive metadata is incomplete.');
        }
        $locked = $this->lockBackupArchive($backupId);
        try {
            $this->performDelete(
                $backup,
                $storage,
                $table,
                $auditScope,
                $subjectType,
                $tenantId,
                $platformUserId,
                $reason,
                $metadata,
                $auditOptions,
                $operation,
            );
        } finally {
            if ($locked) {
                $this->unlockBackupArchive($backupId);
            }
        }
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $auditOptions
     * @param array{
     *   intent_action: string,
     *   failure_action: string,
     *   success_action: string,
     *   final_status: string,
     *   clear_error: bool,
     *   reservation_status: string
     * } $operation
     */
    private function performDelete(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $table,
        string $auditScope,
        string $subjectType,
        ?string $tenantId,
        ?string $platformUserId,
        string $reason,
        array $metadata,
        array $auditOptions,
        array $operation,
    ): void {
        $backupId = trim((string)($backup['id'] ?? ''));
        $objectUri = trim((string)($backup['object_uri'] ?? ''));
        $previousStatus = trim((string)($backup['status'] ?? ''));
        if ($backupId === '' || $objectUri === '' || $previousStatus === '') {
            throw new RuntimeException('Backup archive metadata is incomplete.');
        }

        $now = DateTime::now('UTC')->format('Y-m-d H:i:s');
        $reservationStatus = $operation['reservation_status'];
        $auditMetadata = $metadata;
        $auditMetadata['previous_status'] = $previousStatus;
        $auditMetadata['retry'] = $previousStatus === $reservationStatus;

        try {
            $this->platform->transactional(function () use (
                $table,
                $backupId,
                $objectUri,
                $previousStatus,
                $now,
                $auditScope,
                $subjectType,
                $tenantId,
                $platformUserId,
                $reason,
                $auditMetadata,
                $auditOptions,
                $operation,
                $reservationStatus,
            ): void {
                if ($tenantId !== null) {
                    $this->assertNoActiveTenantArchiveJob($backupId);
                }
                $reserved = $this->platform->update($table, [
                    'status' => $reservationStatus,
                    'modified_at' => $now,
                ], [
                    'id' => $backupId,
                    'status' => $previousStatus,
                    'object_uri' => $objectUri,
                ]);
                if ($reserved->rowCount() !== 1) {
                    throw new RuntimeException('Backup archive is no longer available for deletion.');
                }
                $this->audit->record(
                    $auditScope . '.' . $operation['intent_action'],
                    $platformUserId,
                    $subjectType,
                    $backupId,
                    $reason,
                    $auditMetadata,
                    false,
                    $auditOptions,
                );
            });
        } catch (Throwable $exception) {
            Log::error(sprintf(
                'Backup deletion reservation failed for %s: %s',
                $backupId,
                $exception::class,
            ));
            if (
                $exception instanceof RuntimeException
                && in_array($exception->getMessage(), [
                    'Backup archive is no longer available for deletion.',
                    'A restore using this backup is already queued or running.',
                ], true)
            ) {
                throw $exception;
            }
            throw new RuntimeException('Backup deletion could not be authorized and audited.', 0, $exception);
        }

        try {
            $storage->delete($objectUri);
        } catch (Throwable $exception) {
            Log::error(sprintf('Backup archive deletion failed for %s: %s', $backupId, $exception::class));
            if ($previousStatus !== $reservationStatus) {
                try {
                    $this->platform->update($table, [
                        'status' => $previousStatus,
                        'modified_at' => $now,
                    ], [
                        'id' => $backupId,
                        'status' => $reservationStatus,
                        'object_uri' => $objectUri,
                    ]);
                } catch (Throwable $stateException) {
                    Log::error(sprintf(
                        'Backup deletion state recovery failed for %s: %s',
                        $backupId,
                        $stateException::class,
                    ));
                }
            }
            $failureMetadata = $auditMetadata;
            $failureMetadata['failure_stage'] = 'storage';
            try {
                $this->audit->record(
                    $auditScope . '.' . $operation['failure_action'],
                    $platformUserId,
                    $subjectType,
                    $backupId,
                    $reason,
                    $failureMetadata,
                    true,
                    $auditOptions,
                );
            } catch (Throwable $auditException) {
                Log::error(sprintf(
                    'Backup deletion failure audit failed for %s: %s',
                    $backupId,
                    $auditException::class,
                ));
            }

            throw new RuntimeException('Backup archive could not be deleted from storage.', 0, $exception);
        }

        try {
            $this->platform->transactional(function () use (
                $table,
                $backupId,
                $objectUri,
                $now,
                $auditScope,
                $subjectType,
                $platformUserId,
                $reason,
                $auditMetadata,
                $auditOptions,
                $operation,
                $reservationStatus,
            ): void {
                $finalValues = [
                    'status' => $operation['final_status'],
                    'object_uri' => null,
                    'modified_at' => $now,
                ];
                if ($operation['clear_error']) {
                    $finalValues['error_summary'] = null;
                }
                $finalized = $this->platform->update($table, $finalValues, [
                    'id' => $backupId,
                    'status' => $reservationStatus,
                    'object_uri' => $objectUri,
                ]);
                if ($finalized->rowCount() !== 1) {
                    throw new RuntimeException('Backup deletion metadata could not be finalized.');
                }

                $this->audit->record(
                    $auditScope . '.' . $operation['success_action'],
                    $platformUserId,
                    $subjectType,
                    $backupId,
                    $reason,
                    $auditMetadata,
                    false,
                    $auditOptions,
                );
            });
        } catch (Throwable $exception) {
            Log::error(sprintf('Backup deletion finalization failed for %s: %s', $backupId, $exception::class));
            throw new RuntimeException(
                'The archive was removed, but its deletion record could not be finalized. Retry the deletion.',
                0,
                $exception,
            );
        }
    }

    /**
     * Hold an archive lock across state reservation, storage deletion, and finalization.
     */
    private function lockBackupArchive(string $backupId): bool
    {
        if (!$this->platform->getDriver() instanceof Postgres) {
            return false;
        }
        $this->platform->execute(
            'SELECT pg_advisory_lock(hashtext(:scope))',
            ['scope' => sprintf('backup-archive:%s', $backupId)],
        );

        return true;
    }

    /**
     * Release the session-level archive lock without masking the operation result.
     */
    private function unlockBackupArchive(string $backupId): void
    {
        try {
            $this->platform->execute(
                'SELECT pg_advisory_unlock(hashtext(:scope))',
                ['scope' => sprintf('backup-archive:%s', $backupId)],
            );
        } catch (Throwable $exception) {
            Log::error(sprintf('Backup archive unlock failed for %s: %s', $backupId, $exception::class));
        }
    }

    /**
     * Reject deletion while a restore or drill still references the archive.
     */
    private function assertNoActiveTenantArchiveJob(string $backupId): void
    {
        $backupId = $this->canonicalizeBackupId($backupId);
        if ($this->platform->getDriver() instanceof Postgres) {
            $activeJobId = $this->platform->execute(
                "SELECT id
                   FROM platform_jobs
                  WHERE status IN (:queued, :running)
                    AND job_type IN (:restore, :drill)
                    AND LOWER(BTRIM(BTRIM(parameters ->> 'backup_id'), '{}')) = :backupId
                  LIMIT 1",
                [
                    'queued' => 'queued',
                    'running' => 'running',
                    'restore' => 'tenant_restore',
                    'drill' => 'tenant_restore_drill',
                    'backupId' => $backupId,
                ],
            )->fetchColumn(0);
            if ($activeJobId !== false) {
                throw new RuntimeException('A restore using this backup is already queued or running.');
            }

            return;
        }

        $rows = $this->platform->execute(
            'SELECT parameters
               FROM platform_jobs
              WHERE status IN (:queued, :running)
                AND job_type IN (:restore, :drill)',
            [
                'queued' => 'queued',
                'running' => 'running',
                'restore' => 'tenant_restore',
                'drill' => 'tenant_restore_drill',
            ],
        )->fetchAll('assoc');
        foreach ($rows as $row) {
            $parameters = json_decode((string)($row['parameters'] ?? ''), true);
            if (
                is_array($parameters)
                && $this->canonicalizeBackupId((string)($parameters['backup_id'] ?? '')) === $backupId
            ) {
                throw new RuntimeException('A restore using this backup is already queued or running.');
            }
        }
    }

    /**
     * Normalize UUID text used in persisted job parameters.
     */
    private function canonicalizeBackupId(string $backupId): string
    {
        $backupId = trim($backupId);
        if (preg_match('/^\{([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})\}$/i', $backupId, $matches) === 1) {
            $backupId = $matches[1];
        }

        return strtolower($backupId);
    }
}
