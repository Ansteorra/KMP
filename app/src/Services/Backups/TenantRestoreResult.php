<?php
declare(strict_types=1);

namespace App\Services\Backups;

final class TenantRestoreResult
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $backupId,
        public readonly string $mode,
        public readonly string $sourceTenantSlug,
        public readonly string $targetTenantSlug,
        public readonly string $status,
        public readonly bool $dryRun,
    ) {
    }
}
