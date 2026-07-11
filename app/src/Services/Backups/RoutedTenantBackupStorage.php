<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\KMP\TenantMetadata;

/**
 * Writes new archives to configured storage while retaining historical local:// reads.
 */
final class RoutedTenantBackupStorage implements TenantBackupStorageInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly TenantBackupStorageInterface $configured,
        private readonly TenantBackupStorageInterface $local,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        return $this->configured->workPath($backupId, $suffix);
    }

    /**
     * @inheritDoc
     */
    public function store(
        TenantMetadata $tenant,
        string $backupId,
        string $encryptedPath,
    ): TenantBackupStoredObject {
        return $this->configured->store($tenant, $backupId, $encryptedPath);
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        return $this->adapter($objectUri)->retrieve($objectUri, $destinationPath);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        $this->adapter($objectUri)->delete($objectUri);
    }

    /**
     * Select storage from the persisted object URI scheme.
     */
    private function adapter(string $objectUri): TenantBackupStorageInterface
    {
        return str_starts_with($objectUri, 'local://') ? $this->local : $this->configured;
    }
}
