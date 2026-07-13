<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\TenantMetadata;
use App\Services\Backups\BackupArchiveStorageInterface;
use App\Services\Backups\BackupDownloadService;
use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\Backups\TenantBackupService;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use RuntimeException;

/**
 * Shared guarded-download plumbing for managed (platform-recorded) backups.
 *
 * Used by both the Platform Admin controllers and the tenant self-service
 * Backups controller so archive/recovery-key downloads behave identically
 * regardless of who requests them.
 */
trait ManagedBackupDownloadTrait
{
    /**
     * Validate completed encrypted backup metadata before a guarded action.
     *
     * @param array<string, mixed> $backup
     * @param list<string> $allowedTypes
     */
    protected function assertUsableBackup(
        array $backup,
        array $allowedTypes = [TenantBackupService::BACKUP_TYPE, 'pg_dump'],
    ): void {
        if ((string)($backup['status'] ?? '') !== 'completed') {
            throw new BadRequestException('Only completed backups can be used for this action.');
        }
        if (!in_array((string)($backup['backup_type'] ?? ''), $allowedTypes, true)) {
            throw new BadRequestException('This backup format is not supported for this action.');
        }
        if ((string)($backup['object_uri'] ?? '') === '') {
            throw new BadRequestException('Backup object metadata is incomplete.');
        }
        $backupType = (string)($backup['backup_type'] ?? '');
        if (
            $backupType !== TenantBackupService::LEGACY_BACKUP_TYPE
            && !preg_match('/^[0-9a-f]{64}$/', strtolower((string)($backup['object_sha256'] ?? '')))
        ) {
            throw new BadRequestException('Backup integrity metadata is incomplete.');
        }
        $retentionUntil = trim((string)($backup['retention_until'] ?? ''));
        if ($retentionUntil !== '') {
            $timestamp = strtotime($retentionUntil . ' UTC');
            if ($timestamp !== false && $timestamp <= time()) {
                throw new BadRequestException('This backup has passed its retention period.');
            }
        }
    }

    /**
     * Stage and verify a backup for a streaming file response.
     *
     * @param array<string, mixed> $backup
     * @return array{path: string, filename: string}
     */
    protected function stageBackupDownload(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $filenamePrefix,
    ): array {
        try {
            return (new BackupDownloadService())->stage($backup, $storage, $filenamePrefix);
        } catch (RuntimeException $exception) {
            throw new BadRequestException($exception->getMessage(), null, $exception);
        }
    }

    /**
     * Export a tenant backup recovery-key package.
     *
     * @param array<string, mixed> $backup Backup metadata row
     * @param array<string, mixed> $tenant Tenant metadata row
     * @return array{filename: string, content: string}
     */
    protected function exportTenantBackupRecoveryKey(array $backup, array $tenant): array
    {
        return (new BackupRecoveryKeyService())->exportTenant(
            $backup,
            TenantMetadata::fromPlatformRow($tenant),
            SecretStoreFactory::fromConfig(),
        );
    }

    /**
     * Return a recovery-key attachment that browsers and intermediary caches must not retain.
     *
     * @param array{filename: string, content: string} $export Recovery-key export
     */
    protected function recoveryKeyDownloadResponse(array $export): Response
    {
        return $this->response
            ->withType('application/json')
            ->withDownload($export['filename'])
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withStringBody($export['content']);
    }
}
