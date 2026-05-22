<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Non-sensitive pre-migration checkpoint metadata.
 */
final class TenantMigrationMarkerResult
{
    /**
     * @param string $markerJobId Platform job that recorded the marker
     * @param string|null $backupId Logical backup id associated with the marker
     * @param array<string, mixed> $metadata Scrubbed marker metadata for the migration job
     */
    public function __construct(
        public readonly string $markerJobId,
        public readonly ?string $backupId,
        public readonly array $metadata,
    ) {
    }
}
