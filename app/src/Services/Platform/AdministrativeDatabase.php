<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Core\Configure;
use Cake\Database\Connection;
use RuntimeException;

/**
 * Process-level separation for schema, provisioning, and restore jobs.
 */
final class AdministrativeDatabase
{
    /**
     * Whether this process was explicitly started as a privileged CLI job.
     */
    public static function enabled(): bool
    {
        return PHP_SAPI === 'cli' && Configure::read('Database.adminJob', false) === true;
    }

    /**
     * Fail before attempting privileged operations in an ordinary runtime.
     */
    public static function requireJob(): void
    {
        if (!self::enabled()) {
            throw new RuntimeException('This operation requires a dedicated KMP_ADMIN_JOB process.');
        }
    }

    /**
     * Reuse administrative credentials only for databases on their configured server.
     *
     * @param array<string, mixed> $config Administrative datasource
     * @param string $server Tenant database server
     * @param string $database Tenant database name
     * @return array<string, mixed>
     */
    public static function forTenant(array $config, string $server, string $database): array
    {
        self::requireJob();
        if (strtolower($server) !== strtolower((string)($config['host'] ?? ''))) {
            throw new RuntimeException(
                'Tenant administration requires a job configured for the tenant database server.',
            );
        }
        unset($config['url']);
        $config['className'] = Connection::class;
        $config['database'] = $database;

        return $config;
    }
}
