<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupDumperInterface;
use App\Services\Backups\TenantBackupDumpResult;
use App\Services\Secrets\SensitiveString;

class FakeTenantBackupDumper implements TenantBackupDumperInterface
{
    public function __construct(private readonly string $plaintext)
    {
    }

    public function dump(
        TenantMetadata $tenant,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        file_put_contents($outputPath, $this->plaintext, LOCK_EX);

        return new TenantBackupDumpResult($outputPath, strlen($this->plaintext), ['fake-pg-dump']);
    }
}
