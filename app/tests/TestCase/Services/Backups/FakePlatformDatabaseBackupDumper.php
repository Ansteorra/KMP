<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\PlatformDatabaseBackupDumperInterface;
use App\Services\Backups\TenantBackupDumpResult;
use App\Services\Secrets\SensitiveString;

class FakePlatformDatabaseBackupDumper implements PlatformDatabaseBackupDumperInterface
{
    public function __construct(private readonly string $plaintext)
    {
    }

    public function dump(
        array $platformConfig,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        file_put_contents($outputPath, $this->plaintext, LOCK_EX);

        return new TenantBackupDumpResult($outputPath, strlen($this->plaintext), ['pg_dump']);
    }
}
