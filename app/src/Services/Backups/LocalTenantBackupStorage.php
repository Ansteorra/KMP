<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use RuntimeException;

class LocalTenantBackupStorage implements TenantBackupStorageInterface
{
    public function __construct(
        private readonly string $rootPath,
        private readonly bool $enabled,
    ) {
    }

    public function workPath(string $backupId, string $suffix): string
    {
        $this->assertEnabled();
        $this->assertSafeSegment($backupId, 'backup id');
        $this->assertSafeSuffix($suffix);
        $dir = $this->join($this->rootPath, 'work');
        $this->ensureDirectory($dir);

        return $this->join($dir, $backupId . $suffix);
    }

    public function store(TenantMetadata $tenant, string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        $this->assertEnabled();
        $this->assertSafeSegment($tenant->slug, 'tenant slug');
        $this->assertSafeSegment($backupId, 'backup id');
        if (!is_file($encryptedPath)) {
            throw new RuntimeException('Encrypted backup file is missing.');
        }
        $dir = $this->join($this->join($this->rootPath, 'objects'), $tenant->slug);
        $this->ensureDirectory($dir);
        $target = $this->join($dir, $backupId . '.json.gz.enc');
        if (!rename($encryptedPath, $target)) {
            throw new RuntimeException('Unable to store encrypted backup file.');
        }

        return new TenantBackupStoredObject(
            'local://' . $tenant->slug . '/' . $backupId . '.json.gz.enc',
            (int)filesize($target),
            hash_file('sha256', $target) ?: '',
        );
    }

    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        $this->assertEnabled();
        $this->assertSafePath($destinationPath, 'restore destination path');
        $source = $this->pathFromUri($objectUri);
        if (!is_file($source)) {
            throw new RuntimeException('Stored tenant backup object is missing.');
        }
        $this->ensureDirectory(dirname($destinationPath));
        if (!copy($source, $destinationPath)) {
            throw new RuntimeException('Unable to retrieve stored tenant backup object.');
        }

        return new TenantBackupStoredObject(
            $objectUri,
            (int)filesize($destinationPath),
            hash_file('sha256', $destinationPath) ?: '',
        );
    }

    public function delete(string $objectUri): void
    {
        $this->assertEnabled();
        $path = $this->pathFromUri($objectUri);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to delete stored tenant backup object.');
        }
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new RuntimeException(
                'Local tenant backup storage is disabled. Enable only in local/dev/test configuration.',
            );
        }
    }

    private function assertSafeSegment(string $segment, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $segment)) {
            throw new RuntimeException(sprintf('Unsafe %s for local backup storage.', $label));
        }
    }

    private function assertSafeSuffix(string $suffix): void
    {
        if (!preg_match('/^\.[A-Za-z0-9._-]+$/', $suffix)) {
            throw new RuntimeException('Unsafe backup work file suffix.');
        }
    }

    private function assertSafePath(string $path, string $label): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException(sprintf('Unsafe tenant backup %s.', $label));
        }
    }

    private function pathFromUri(string $objectUri): string
    {
        if (
            !preg_match(
                '#^local://([^/]+)/([^/]+\.(?:json\.gz|pgdump)\.enc(?:\.json)?)$#',
                $objectUri,
                $matches,
            )
        ) {
            throw new RuntimeException('Unsupported or unsafe tenant backup object URI.');
        }
        $this->assertSafeSegment($matches[1], 'tenant slug');
        $backupId = preg_replace('/\.(?:json\.gz|pgdump)\.enc(?:\.json)?$/', '', $matches[2]);
        $this->assertSafeSegment((string)$backupId, 'backup id');

        return $this->join($this->join($this->rootPath, 'objects'), $matches[1]) . DIRECTORY_SEPARATOR . $matches[2];
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create local backup storage directory.');
        }
    }

    private function join(string $left, string $right): string
    {
        return rtrim($left, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($right, DIRECTORY_SEPARATOR);
    }
}
