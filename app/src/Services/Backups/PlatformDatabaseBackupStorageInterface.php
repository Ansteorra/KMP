<?php
declare(strict_types=1);

namespace App\Services\Backups;

interface PlatformDatabaseBackupStorageInterface extends BackupArchiveStorageInterface
{
    /**
     * Persist an encrypted platform database archive.
     */
    public function store(string $backupId, string $encryptedPath): TenantBackupStoredObject;
}
