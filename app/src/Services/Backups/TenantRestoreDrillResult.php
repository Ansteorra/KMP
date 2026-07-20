<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

class TenantRestoreDrillResult
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $backupId,
        public readonly string $tenantSlug,
        public readonly string $status,
        public readonly bool $dryRun,
        public readonly string $message,
    ) {
    }
}
