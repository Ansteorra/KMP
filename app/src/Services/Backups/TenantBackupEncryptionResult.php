<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

final class TenantBackupEncryptionResult
{
    /**
     * @param array<string, mixed> $wrappedDekMetadata Metadata needed to unwrap the DEK
     */
    public function __construct(
        public readonly string $encryptedPath,
        public readonly string $algorithm,
        public readonly string $wrappedDek,
        public readonly array $wrappedDekMetadata,
    ) {
    }
}
