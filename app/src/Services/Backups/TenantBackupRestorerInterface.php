<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SensitiveString;

interface TenantBackupRestorerInterface
{
    /**
     * Validate a decrypted tenant backup and its target without mutating data.
     */
    public function validate(TenantMetadata $targetTenant, string $backupPath): void;

    /**
     * Restore a decrypted tenant backup into the target tenant database.
     *
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @return array<string, mixed> Restore statistics
     */
    public function restore(
        TenantMetadata $targetTenant,
        SensitiveString $databasePassword,
        string $backupPath,
        ?callable $progressReporter = null,
    ): array;
}
