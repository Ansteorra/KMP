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
     * @return int Number of work items processed or dispatched
     */
    public function dispatch(array $schedule, ?TenantMetadata $tenant): int;
}
