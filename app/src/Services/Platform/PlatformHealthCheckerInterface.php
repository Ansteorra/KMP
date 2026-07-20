<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Reports platform metadata database availability for tenant-aware entry points.
 */
interface PlatformHealthCheckerInterface
{
    /**
     * Check platform metadata database availability.
     *
     * @return \App\Services\Platform\PlatformHealthStatus
     */
    public function check(): PlatformHealthStatus;
}
