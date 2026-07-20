<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SensitiveString;

interface TenantBackupDumperInterface
{
    public function dump(
        TenantMetadata $tenant,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult;
}
