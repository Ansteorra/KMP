<?php
declare(strict_types=1);

namespace App\Services\Platform;

final class TenantMigrationResult
{
    /**
     * Constructor.
     *
     * @param string|null $schemaVersion Latest tenant schema version
     * @param array<string, mixed> $metadata Non-sensitive result metadata
     */
    public function __construct(
        public readonly ?string $schemaVersion,
        public readonly array $metadata = [],
    ) {
    }
}
