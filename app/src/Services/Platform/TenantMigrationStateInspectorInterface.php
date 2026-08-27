<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;

interface TenantMigrationStateInspectorInterface
{
    /**
     * Inspect one tenant database against the current release migration catalog.
     */
    public function inspect(TenantMetadata $tenant): TenantMigrationState;
}
