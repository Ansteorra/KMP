<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Secrets\SecretStoreFactory;
use Throwable;

/**
 * Reports non-sensitive backup execution prerequisites.
 */
final class BackupReadinessService
{
    /**
     * @return array{ready: bool, checks: list<array{name: string, ready: bool, detail: string}>}
     */
    public function check(): array
    {
        $checks = [
            $this->jsonEngineCheck(),
            $this->binaryCheck('pg_dump'),
            $this->storageCheck(),
            $this->platformKeyCheck(),
        ];

        return [
            'ready' => array_reduce(
                $checks,
                static fn(bool $ready, array $check): bool => $ready && $check['ready'],
                true,
            ),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, ready: bool, detail: string}
     */
    private function jsonEngineCheck(): array
    {
        $ready = function_exists('json_encode')
            && function_exists('gzencode')
            && function_exists('gzdecode');

        return [
            'name' => 'tenant_json_backup_engine',
            'ready' => $ready,
            'detail' => $ready
                ? 'JSON encoding and gzip compression are available for tenant backups.'
                : 'The PHP JSON or zlib extension is unavailable.',
        ];
    }

    /**
     * @return array{name: string, ready: bool, detail: string}
     */
    private function binaryCheck(string $binary): array
    {
        $ready = $this->findExecutable($binary) !== null;

        return [
            'name' => $binary,
            'ready' => $ready,
            'detail' => $ready ? 'Available to the platform worker.' : 'Not found in the worker PATH.',
        ];
    }

    /**
     * @return array{name: string, ready: bool, detail: string}
     */
    private function storageCheck(): array
    {
        try {
            BackupStorageFactory::tenant()->workPath('00000000-0000-4000-8000-000000000000', '.ready');
            BackupStorageFactory::platform()->workPath('00000000-0000-4000-8000-000000000000', '.ready');

            return [
                'name' => 'backup_storage',
                'ready' => true,
                'detail' => 'Configured storage and staging directory are available.',
            ];
        } catch (Throwable) {
            return [
                'name' => 'backup_storage',
                'ready' => false,
                'detail' => 'Configured storage or staging directory is unavailable.',
            ];
        }
    }

    /**
     * @return array{name: string, ready: bool, detail: string}
     */
    private function platformKeyCheck(): array
    {
        try {
            $secret = SecretStoreFactory::fromConfig()->get('platform.backup.kek');
            $ready = $secret !== null && !$secret->isEmpty();
        } catch (Throwable) {
            $ready = false;
        }

        return [
            'name' => 'platform_backup_key',
            'ready' => $ready,
            'detail' => $ready
                ? 'Platform backup encryption key is available.'
                : 'Platform backup encryption key is unavailable.',
        ];
    }

    /**
     * Locate an executable without invoking a shell.
     */
    private function findExecutable(string $binary): ?string
    {
        $path = getenv('PATH');
        if (!is_string($path) || $path === '') {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
