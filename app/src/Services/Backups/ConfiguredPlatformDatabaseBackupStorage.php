<?php
declare(strict_types=1);

namespace App\Services\Backups;

final class ConfiguredPlatformDatabaseBackupStorage implements PlatformDatabaseBackupStorageInterface
{
    /**
     * Constructor.
     */
    public function __construct(private readonly BackupObjectStorage $objects)
    {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        return $this->objects->workPath($backupId, $suffix);
    }

    /**
     * @inheritDoc
     */
    public function store(string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        return $this->objects->store(sprintf('platform/%s.pgdump.enc', $backupId), $encryptedPath);
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        return $this->objects->retrieve($objectUri, 'platform/', $destinationPath);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        $this->objects->delete($objectUri, 'platform/');
    }
}
