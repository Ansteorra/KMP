<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

final class TenantBackupDumpResult
{
    /**
     * @param list<string> $argv Redacted argv used for the dump
     */
    public function __construct(
        public readonly string $path,
        public readonly int $sizeBytes,
        public readonly array $argv,
    ) {
    }
}
