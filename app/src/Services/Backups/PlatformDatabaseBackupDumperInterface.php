<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Secrets\SensitiveString;

interface PlatformDatabaseBackupDumperInterface
{
    /**
     * @param array<string, mixed> $platformConfig Platform datasource configuration
     */
    public function dump(
        array $platformConfig,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult;
}
