<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\BackupService;
use App\Services\Secrets\SensitiveString;
use App\Services\TenantConnectionManager;
use Cake\Datasource\ConnectionManager;
use Closure;
use RuntimeException;

/**
 * Validates and restores KMP JSON logical archives inside a tenant scope.
 */
final class JsonTenantBackupRestorer implements TenantBackupRestorerInterface
{
    /**
     * @var \Closure():void
     */
    private Closure $migrationRunner;

    /**
     * @var \Closure():\App\Services\BackupService
     */
    private Closure $backupServiceFactory;

    public function __construct(
        private readonly TenantConnectionManager $tenantConnections,
        callable $migrationRunner,
        ?callable $backupServiceFactory = null,
    ) {
        $this->migrationRunner = Closure::fromCallable($migrationRunner);
        $this->backupServiceFactory = $backupServiceFactory === null
            ? static fn(): BackupService => new BackupService()
            : Closure::fromCallable($backupServiceFactory);
    }

    public function validate(TenantMetadata $targetTenant, string $backupPath): void
    {
        $archive = $this->readArchive($backupPath);
        $this->tenantConnections->withTenant($targetTenant, function () use ($archive): void {
            ConnectionManager::get('default')->execute('SELECT 1');
            $this->backupService()->validateLogicalArchive($archive);
        });
    }

    public function restore(
        TenantMetadata $targetTenant,
        SensitiveString $databasePassword,
        string $backupPath,
        ?callable $progressReporter = null,
    ): array {
        if ($databasePassword->isEmpty()) {
            throw new RuntimeException('Tenant database password is unavailable for JSON restore.');
        }
        $archive = $this->readArchive($backupPath);

        return $this->tenantConnections->withTenant(
            $targetTenant,
            fn(): array => $this->backupService()->importLogicalArchive(
                $archive,
                $progressReporter,
                [],
                $this->migrationRunner,
            ),
        );
    }

    private function backupService(): BackupService
    {
        $service = ($this->backupServiceFactory)();
        if (!$service instanceof BackupService) {
            throw new RuntimeException('JSON backup service factory returned an invalid service.');
        }

        return $service;
    }

    private function readArchive(string $backupPath): string
    {
        if (!is_file($backupPath)) {
            throw new RuntimeException('Decrypted tenant JSON backup file is missing.');
        }
        $archive = file_get_contents($backupPath);
        if ($archive === false || $archive === '') {
            throw new RuntimeException('Unable to read the tenant JSON backup archive.');
        }

        return $archive;
    }
}
