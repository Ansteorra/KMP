<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;

interface TenantMigrationMarkerServiceInterface
{
    /**
     * Create a required pre-migration recovery marker before tenant DDL runs.
     *
     * @param array<string, mixed> $options Tenant migration options
     */
    public function createMarker(
        TenantMetadata $tenant,
        array $options,
        string $migrationJobId,
    ): TenantMigrationMarkerResult;
}
