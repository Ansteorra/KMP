<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Core\Configure;

final class PlatformAdminHost
{
    /** Check the configured reserved origins before reading any session. */
    public static function allows(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        $hosts = array_map(
            static fn($value): string => strtolower(rtrim(trim((string)$value), '.')),
            (array)Configure::read('Platform.adminPortal.hosts', []),
        );

        return $host !== '' && in_array($host, $hosts, true);
    }
}
