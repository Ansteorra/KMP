<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupDumperInterface;
use App\Services\Backups\TenantBackupDumpResult;
use App\Services\Secrets\SensitiveString;
use RuntimeException;

class FailingTenantBackupDumper implements TenantBackupDumperInterface
{
    public function __construct(private readonly string $message)
    {
    }

    public function dump(
        TenantMetadata $tenant,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        throw new RuntimeException($this->message);
    }
}
