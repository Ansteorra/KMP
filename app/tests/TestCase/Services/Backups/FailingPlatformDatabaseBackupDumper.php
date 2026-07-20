<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\PlatformDatabaseBackupDumperInterface;
use App\Services\Backups\TenantBackupDumpResult;
use App\Services\Secrets\SensitiveString;
use RuntimeException;

class FailingPlatformDatabaseBackupDumper implements PlatformDatabaseBackupDumperInterface
{
    public function __construct(private readonly string $message)
    {
    }

    public function dump(
        array $platformConfig,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        throw new RuntimeException($this->message);
    }
}
