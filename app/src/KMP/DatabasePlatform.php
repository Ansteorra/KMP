<?php
declare(strict_types=1);

namespace App\KMP;

use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/** Enforces the PostgreSQL-only platform contract before opening connections. */
final class DatabasePlatform
{
    /**
     * Validate effective datasource drivers, including overrides embedded in URLs.
     *
     * @param array<string, mixed> $configurations Merged application datasources.
     */
    public static function assertSupported(array $configurations): void
    {
        foreach (['default', 'test', 'platform', 'test_platform'] as $name) {
            $config = $configurations[$name] ?? [];
            if (!is_array($config)) {
                throw new RuntimeException('KMP requires PostgreSQL datasource configuration.');
            }
            if (!empty($config['url'])) {
                try {
                    $scheme = strtolower((string)parse_url((string)$config['url'], PHP_URL_SCHEME));
                    if (!in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
                        throw new RuntimeException('Unsupported datasource URL scheme.');
                    }
                    $url = preg_replace('/^(postgresql|pgsql):/i', 'postgres:', (string)$config['url']);
                    $config = ConnectionManager::parseDsn($url) + $config;
                } catch (Throwable) {
                    // Never include a credential-bearing URL in configuration errors.
                    throw new RuntimeException('Invalid PostgreSQL datasource URL for ' . $name . '.');
                }
            }
            $driver = $config['driver'] ?? null;
            if ($driver !== Postgres::class && !$driver instanceof Postgres) {
                throw new RuntimeException('KMP supports PostgreSQL only; check datasource ' . $name . '.');
            }
        }
    }
}
