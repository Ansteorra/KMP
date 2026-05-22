<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

class TenantRestoreDrillPlan
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $backupId,
        public readonly string $tenantId,
        public readonly string $tenantSlug,
        public readonly ?string $backupCompletedAt,
        public readonly bool $dryRun,
        public readonly bool $destructiveExecution,
    ) {
    }
}
