<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;

interface TenantBackupStorageInterface
{
    public function workPath(string $backupId, string $suffix): string;

    public function store(TenantMetadata $tenant, string $backupId, string $encryptedPath): TenantBackupStoredObject;

    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject;
}
