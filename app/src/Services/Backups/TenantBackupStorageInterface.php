<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;

interface TenantBackupStorageInterface extends BackupArchiveStorageInterface
{
    public function store(TenantMetadata $tenant, string $backupId, string $encryptedPath): TenantBackupStoredObject;
}
