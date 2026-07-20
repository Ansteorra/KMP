<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

final class TenantBackupResult
{
    public function __construct(
        public readonly string $backupId,
        public readonly string $jobId,
        public readonly string $status,
        public readonly ?string $objectUri,
    ) {
    }
}
