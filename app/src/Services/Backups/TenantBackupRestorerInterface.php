<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SensitiveString;

interface TenantBackupRestorerInterface
{
    /**
     * Restore a decrypted tenant backup into the target tenant database.
     */
    public function restore(TenantMetadata $targetTenant, SensitiveString $databasePassword, string $backupPath): void;

    /**
     * Build a safe argv list for restore planning and validation.
     *
     * @return list<string>
     */
    public function buildArgv(TenantMetadata $targetTenant, string $backupPath): array;
}
