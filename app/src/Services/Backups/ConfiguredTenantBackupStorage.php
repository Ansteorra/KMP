<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\KMP\TenantMetadata;

final class ConfiguredTenantBackupStorage implements TenantBackupStorageInterface
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
    public function store(TenantMetadata $tenant, string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        return $this->objects->store(
            sprintf('tenants/%s/%s.json.gz.enc', $tenant->slug, $backupId),
            $encryptedPath,
        );
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        return $this->objects->retrieve($objectUri, 'tenants/', $destinationPath);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        $this->objects->delete($objectUri, 'tenants/');
    }
}
