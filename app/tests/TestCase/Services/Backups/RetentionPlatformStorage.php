<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\PlatformDatabaseBackupStorageInterface;
use App\Services\Backups\TenantBackupStoredObject;
use RuntimeException;

final class RetentionPlatformStorage implements PlatformDatabaseBackupStorageInterface
{
    /**
     * @var list<string>
     */
    public array $deleted = [];

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        return TMP . $backupId . $suffix;
    }

    /**
     * @inheritDoc
     */
    public function store(string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        throw new RuntimeException('Not used.');
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        throw new RuntimeException('Not used.');
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        $this->deleted[] = $objectUri;
    }
}
