<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\BackupService;
use App\Services\Secrets\SensitiveString;
use App\Services\TenantConnectionManager;
use Closure;
use RuntimeException;

/**
 * Creates KMP's gzip-compressed JSON logical archive for one tenant.
 */
final class JsonTenantBackupDumper implements TenantBackupDumperInterface
{
    /**
     * @var \Closure():\App\Services\BackupService
     */
    private Closure $backupServiceFactory;

    public function __construct(
        private readonly TenantConnectionManager $tenantConnections,
        ?callable $backupServiceFactory = null,
    ) {
        $this->backupServiceFactory = $backupServiceFactory === null
            ? static fn(): BackupService => new BackupService()
            : Closure::fromCallable($backupServiceFactory);
    }

    public function dump(
        TenantMetadata $tenant,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        if ($databasePassword->isEmpty()) {
            throw new RuntimeException('Tenant database password is unavailable for JSON backup.');
        }
        $archive = $this->tenantConnections->withTenant(
            $tenant,
            function (): array {
                $service = ($this->backupServiceFactory)();
                if (!$service instanceof BackupService) {
                    throw new RuntimeException('JSON backup service factory returned an invalid service.');
                }

                return $service->exportLogicalArchive();
            },
        );
        $data = $archive['data'] ?? null;
        if (!is_string($data) || $data === '') {
            throw new RuntimeException('JSON backup engine returned an empty archive.');
        }
        $written = file_put_contents($outputPath, $data, LOCK_EX);
        if ($written === false || $written !== strlen($data)) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw new RuntimeException('Unable to write the tenant JSON backup archive.');
        }
        if (!chmod($outputPath, 0600)) {
            unlink($outputPath);
            throw new RuntimeException('Unable to secure the tenant JSON backup archive.');
        }

        return new TenantBackupDumpResult(
            $outputPath,
            $written,
            ['kmp-json-export', '--tenant', $tenant->slug],
        );
    }
}
