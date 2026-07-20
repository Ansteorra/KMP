<?php
declare(strict_types=1);

namespace App\Services\Backups;

interface BackupArchiveStorageInterface
{
    /**
     * Return an isolated local staging path for an archive.
     */
    public function workPath(string $backupId, string $suffix): string;

    /**
     * Retrieve and verify basic metadata for an encrypted archive.
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject;

    /**
     * Delete a stored encrypted archive.
     */
    public function delete(string $objectUri): void;
}
