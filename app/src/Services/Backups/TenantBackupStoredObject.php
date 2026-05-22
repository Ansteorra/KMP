<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

final class TenantBackupStoredObject
{
    public function __construct(
        public readonly string $uri,
        public readonly int $sizeBytes,
        public readonly string $sha256,
    ) {
    }
}
