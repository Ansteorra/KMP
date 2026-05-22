<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

use RuntimeException;

class LocalPlatformDatabaseBackupStorage
{
    public function __construct(
        private readonly string $rootPath,
        private readonly bool $enabled,
        private readonly string $environment = 'production',
        private readonly array $allowInEnvironments = ['local', 'development', 'dev', 'test', 'ci'],
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

    public function store(string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        $this->assertEnabled();
        $this->assertSafeSegment($backupId, 'backup id');
        if (!is_file($encryptedPath)) {
            throw new RuntimeException('Encrypted platform backup file is missing.');
        }
        $dir = $this->join($this->rootPath, 'objects' . DIRECTORY_SEPARATOR . 'platform');
        $this->ensureDirectory($dir);
        $target = $this->join($dir, $backupId . '.pgdump.enc.json');
        if (!rename($encryptedPath, $target)) {
            throw new RuntimeException('Unable to store encrypted platform backup file.');
        }

        return new TenantBackupStoredObject(
            'local://platform/' . $backupId . '.pgdump.enc.json',
            (int)filesize($target),
            hash_file('sha256', $target) ?: '',
        );
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new RuntimeException(
                'Local platform backup storage is disabled. Enable only in local/dev/test configuration.',
            );
        }
        if (!in_array(strtolower($this->environment), array_map('strtolower', $this->allowInEnvironments), true)) {
            throw new RuntimeException(
                'Local platform backup storage is not allowed outside local/dev/test configuration.',
            );
        }
    }

    private function assertSafeSegment(string $segment, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $segment)) {
            throw new RuntimeException(sprintf('Unsafe %s for local platform backup storage.', $label));
        }
    }

    private function assertSafeSuffix(string $suffix): void
    {
        if (!preg_match('/^\.[A-Za-z0-9._-]+$/', $suffix)) {
            throw new RuntimeException('Unsafe platform backup work file suffix.');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create local platform backup storage directory.');
        }
    }

    private function join(string $left, string $right): string
    {
        return rtrim($left, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($right, DIRECTORY_SEPARATOR);
    }
}
