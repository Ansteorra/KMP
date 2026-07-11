<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupRestorerInterface;
use App\Services\Secrets\SensitiveString;

class RecordingTenantBackupRestorer implements TenantBackupRestorerInterface
{
    /**
     * @var list<array{tenant: string, database: string, backupPath: string}>
     */
    public array $validationCalls = [];

    /**
     * @var list<array{tenant: string, passwordLength: int, backupPath: string}>
     */
    public array $restoreCalls = [];

    public function validate(TenantMetadata $targetTenant, string $backupPath): void
    {
        $this->validationCalls[] = [
            'tenant' => $targetTenant->slug,
            'database' => $targetTenant->dbName,
            'backupPath' => $backupPath,
        ];
    }

    public function restore(
        TenantMetadata $targetTenant,
        SensitiveString $databasePassword,
        string $backupPath,
        ?callable $progressReporter = null,
    ): array {
        $this->restoreCalls[] = [
            'tenant' => $targetTenant->slug,
            'passwordLength' => $databasePassword->length(),
            'backupPath' => $backupPath,
        ];
        if ($progressReporter !== null) {
            $progressReporter(['phase' => 'completed']);
        }

        return ['table_count' => 1, 'row_count' => 1];
    }
}
