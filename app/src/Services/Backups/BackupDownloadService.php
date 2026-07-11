<?php
declare(strict_types=1);

namespace App\Services\Backups;

use RuntimeException;
use Throwable;

final class BackupDownloadService
{
    /**
     * @param array<string, mixed> $backup
     * @return array{path: string, filename: string}
     */
    public function stage(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $filenamePrefix,
    ): array {
        $backupId = strtolower(trim((string)($backup['id'] ?? '')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $backupId)) {
            throw new RuntimeException('Backup identifier is invalid.');
        }
        $backupType = (string)($backup['backup_type'] ?? '');
        $archiveSuffix = match ($backupType) {
            TenantBackupService::BACKUP_TYPE => '.json.gz.enc',
            TenantBackupService::LEGACY_BACKUP_TYPE => '.kmpbackup',
            'pg_dump' => '.pgdump.enc',
            default => throw new RuntimeException('Backup type is not downloadable.'),
        };

        $stagingId = sprintf('%s-%s', $backupId, bin2hex(random_bytes(8)));
        $destination = $storage->workPath($stagingId, '.download' . $archiveSuffix);
        try {
            $stored = $storage->retrieve((string)($backup['object_uri'] ?? ''), $destination);
            $expectedSha256 = strtolower((string)($backup['object_sha256'] ?? ''));
            if ($expectedSha256 === '' && $backupType !== TenantBackupService::LEGACY_BACKUP_TYPE) {
                throw new RuntimeException('Backup checksum metadata is missing.');
            }
            if ($expectedSha256 !== '' && !hash_equals($expectedSha256, strtolower($stored->sha256))) {
                throw new RuntimeException('Stored backup checksum does not match metadata.');
            }
            $expectedSize = (int)($backup['object_size_bytes'] ?? 0);
            if ($expectedSize > 0 && $expectedSize !== $stored->sizeBytes) {
                throw new RuntimeException('Stored backup size does not match metadata.');
            }
        } catch (Throwable $exception) {
            if (is_file($destination)) {
                unlink($destination);
            }
            throw $exception;
        }

        $safePrefix = strtolower(trim($filenamePrefix));
        $safePrefix = trim((string)preg_replace('/[^a-z0-9-]+/', '-', $safePrefix), '-');
        if ($safePrefix === '') {
            $safePrefix = 'backup';
        }

        return [
            'path' => $destination,
            'filename' => sprintf('%s-%s%s', $safePrefix, $backupId, $archiveSuffix),
        ];
    }
}
