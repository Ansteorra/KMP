<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\BackupStorageService;
use RuntimeException;
use Throwable;

/**
 * Provides bounded, path-safe access to archives created by the former .kmpbackup workflow.
 */
final class LegacyKmpBackupStorage implements BackupArchiveStorageInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly BackupStorageService $storage,
        private readonly string $workRoot = TMP . 'platform-backup-work',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $backupId)) {
            throw new RuntimeException('Unsafe legacy backup identifier.');
        }
        if (!preg_match('/^\.[A-Za-z0-9._-]+$/', $suffix)) {
            throw new RuntimeException('Unsafe legacy backup work file suffix.');
        }
        $this->ensureDirectory($this->workRoot);

        return rtrim($this->workRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $backupId . $suffix;
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        $objectName = $this->objectName($objectUri);
        if ($destinationPath === '' || str_contains($destinationPath, "\0")) {
            throw new RuntimeException('Unsafe legacy backup destination path.');
        }
        $this->ensureDirectory(dirname($destinationPath));
        $source = $this->storage->readStream($objectName);
        $destination = fopen($destinationPath, 'wb');
        if (!is_resource($destination)) {
            fclose($source);
            throw new RuntimeException('Unable to open legacy backup destination.');
        }

        try {
            if (stream_copy_to_stream($source, $destination) === false) {
                throw new RuntimeException('Unable to retrieve legacy backup object.');
            }
        } catch (Throwable $exception) {
            if (is_file($destinationPath)) {
                unlink($destinationPath);
            }
            throw $exception;
        } finally {
            fclose($source);
            fclose($destination);
        }

        $size = filesize($destinationPath);
        $sha256 = hash_file('sha256', $destinationPath);
        if ($size === false || $sha256 === false) {
            if (is_file($destinationPath)) {
                unlink($destinationPath);
            }
            throw new RuntimeException('Unable to verify retrieved legacy backup object.');
        }

        return new TenantBackupStoredObject($objectUri, (int)$size, $sha256);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        $this->storage->delete($this->objectName($objectUri));
    }

    /**
     * Validate a legacy object name without allowing path traversal.
     */
    private function objectName(string $objectUri): string
    {
        $objectName = trim($objectUri);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}\.kmpbackup$/', $objectName)) {
            throw new RuntimeException('Unsupported or unsafe legacy backup object name.');
        }

        return $objectName;
    }

    /**
     * Create a private staging directory when needed.
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create legacy backup work directory.');
        }
    }
}
