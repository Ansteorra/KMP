<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\BackupStorageService;
use RuntimeException;

/**
 * Stores encrypted backup archives through KMP's configured Flysystem backend.
 */
final class BackupObjectStorage
{
    private const URI_PREFIX = 'backup://';

    /**
     * Constructor.
     */
    public function __construct(
        private readonly BackupStorageService $storage,
        private readonly string $workRoot = TMP . 'platform-backup-work',
    ) {
    }

    /**
     * Return an isolated local staging path.
     */
    public function workPath(string $backupId, string $suffix): string
    {
        $this->assertSafeSegment($backupId, 'backup id');
        if (!preg_match('/^\.[A-Za-z0-9._-]+$/', $suffix)) {
            throw new RuntimeException('Unsafe backup work file suffix.');
        }

        $this->ensureDirectory($this->workRoot);

        return rtrim($this->workRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $backupId . $suffix;
    }

    /**
     * Persist an encrypted archive and remove its staging file.
     */
    public function store(string $objectPath, string $encryptedPath): TenantBackupStoredObject
    {
        $objectPath = $this->validatedObjectPath($objectPath);
        if (!is_file($encryptedPath)) {
            throw new RuntimeException('Encrypted backup file is missing.');
        }

        $stream = fopen($encryptedPath, 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('Unable to open encrypted backup file for storage.');
        }

        try {
            $this->storage->writeStream($objectPath, $stream);
        } finally {
            fclose($stream);
        }

        $size = filesize($encryptedPath);
        $sha256 = hash_file('sha256', $encryptedPath);
        if ($size === false || $sha256 === false) {
            $this->storage->delete($objectPath);
            throw new RuntimeException('Unable to verify stored backup object.');
        }
        if (!unlink($encryptedPath)) {
            $this->storage->delete($objectPath);
            throw new RuntimeException('Unable to remove staged encrypted backup file.');
        }

        return new TenantBackupStoredObject(self::URI_PREFIX . $objectPath, (int)$size, $sha256);
    }

    /**
     * Retrieve an encrypted archive into a safe staging destination.
     */
    public function retrieve(
        string $objectUri,
        string $expectedPrefix,
        string $destinationPath,
    ): TenantBackupStoredObject {
        $objectPath = $this->objectPathFromUri($objectUri, $expectedPrefix);
        $this->assertSafeDestination($destinationPath);
        $this->ensureDirectory(dirname($destinationPath));

        $source = $this->storage->readStream($objectPath);
        $destination = fopen($destinationPath, 'wb');
        if (!is_resource($destination)) {
            fclose($source);
            throw new RuntimeException('Unable to open backup restore destination.');
        }

        try {
            if (stream_copy_to_stream($source, $destination) === false) {
                throw new RuntimeException('Unable to retrieve stored backup object.');
            }
        } finally {
            fclose($source);
            fclose($destination);
        }

        $size = filesize($destinationPath);
        $sha256 = hash_file('sha256', $destinationPath);
        if ($size === false || $sha256 === false) {
            if (is_file($destinationPath) && !unlink($destinationPath)) {
                throw new RuntimeException('Unable to remove an unverifiable backup object.');
            }
            throw new RuntimeException('Unable to verify retrieved backup object.');
        }

        return new TenantBackupStoredObject($objectUri, (int)$size, $sha256);
    }

    /**
     * Delete an encrypted archive from its expected scope.
     */
    public function delete(string $objectUri, string $expectedPrefix): void
    {
        $this->storage->delete($this->objectPathFromUri($objectUri, $expectedPrefix));
    }

    /**
     * Resolve and scope-check a backup object URI.
     */
    private function objectPathFromUri(string $objectUri, string $expectedPrefix): string
    {
        if (!str_starts_with($objectUri, self::URI_PREFIX)) {
            throw new RuntimeException('Unsupported backup object URI.');
        }

        $objectPath = $this->validatedObjectPath(substr($objectUri, strlen(self::URI_PREFIX)));
        if (!str_starts_with($objectPath, $expectedPrefix)) {
            throw new RuntimeException('Backup object URI does not match its expected scope.');
        }

        return $objectPath;
    }

    /**
     * Validate the configured-storage object key.
     */
    private function validatedObjectPath(string $objectPath): string
    {
        if (
            !preg_match(
                '#^(?:platform/[0-9a-f-]{36}\.pgdump\.enc(?:\.json)?'
                . '|tenants/[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?/'
                . '[0-9a-f-]{36}\.(?:json\.gz|pgdump)\.enc(?:\.json)?)$#',
                $objectPath,
            )
        ) {
            throw new RuntimeException('Unsafe backup object path.');
        }

        return $objectPath;
    }

    /**
     * Reject unsafe local path segments.
     */
    private function assertSafeSegment(string $segment, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $segment)) {
            throw new RuntimeException(sprintf('Unsafe %s for backup storage.', $label));
        }
    }

    /**
     * Reject invalid staging destinations.
     */
    private function assertSafeDestination(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Unsafe backup destination path.');
        }
    }

    /**
     * Create a private staging directory when needed.
     */
    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create backup work directory.');
        }
    }
}
