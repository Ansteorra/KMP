<?php
declare(strict_types=1);

namespace App\Services\Backups;

/**
 * Writes new platform archives to configured storage while retaining historical local:// reads.
 */
final class RoutedPlatformDatabaseBackupStorage implements PlatformDatabaseBackupStorageInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly PlatformDatabaseBackupStorageInterface $configured,
        private readonly PlatformDatabaseBackupStorageInterface $local,
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
    public function store(string $backupId, string $encryptedPath): TenantBackupStoredObject
    {
        return $this->configured->store($backupId, $encryptedPath);
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
    private function adapter(string $objectUri): PlatformDatabaseBackupStorageInterface
    {
        return str_starts_with($objectUri, 'local://') ? $this->local : $this->configured;
    }
}
