<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;

interface PlatformScheduleDispatcherInterface
{
    /**
     * Dispatch an allowlisted platform schedule command.
     *
     * @param array<string, mixed> $schedule Platform schedule row
     * @param \App\KMP\TenantMetadata|null $tenant Tenant target, if any
     * @return void
     */
    public function dispatch(array $schedule, ?TenantMetadata $tenant): void;
}
