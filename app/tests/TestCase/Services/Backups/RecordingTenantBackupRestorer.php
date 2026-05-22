<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupRestorerInterface;
use App\Services\Secrets\SensitiveString;

class RecordingTenantBackupRestorer implements TenantBackupRestorerInterface
{
    /**
     * @var list<list<string>>
     */
    public array $argvCalls = [];

    /**
     * @var list<array{tenant: string, passwordLength: int, backupPath: string}>
     */
    public array $restoreCalls = [];

    public function buildArgv(TenantMetadata $targetTenant, string $backupPath): array
    {
        $argv = [
            'fake-pg-restore',
            '--host',
            $targetTenant->dbServer,
            '--username',
            $targetTenant->dbRole,
            '--dbname',
            $targetTenant->dbName,
            $backupPath,
        ];
        $this->argvCalls[] = $argv;

        return $argv;
    }

    public function restore(TenantMetadata $targetTenant, SensitiveString $databasePassword, string $backupPath): void
    {
        $this->restoreCalls[] = [
            'tenant' => $targetTenant->slug,
            'passwordLength' => $databasePassword->length(),
            'backupPath' => $backupPath,
        ];
    }
}
