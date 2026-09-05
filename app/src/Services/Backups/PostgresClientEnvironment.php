<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\Secrets\SensitiveString;

/** Apply the selected datasource's credentials and TLS policy to libpq subprocesses. */
final class PostgresClientEnvironment
{
    /**
     * @param array<string, mixed> $config Selected datasource configuration
     * @param \App\Services\Secrets\SensitiveString $password Selected database password
     * @return array<string, string> Process environment; never log or persist this value
     */
    public static function fromConfig(array $config, SensitiveString $password): array
    {
        $processEnv = getenv();
        $env = is_array($processEnv) ? $processEnv : [];
        // PGHOSTADDR and PGSERVICE can override command-line connection selection.
        // No inherited libpq setting may weaken the selected TLS policy either.
        foreach (array_keys($env) as $key) {
            if (str_starts_with($key, 'PG')) {
                unset($env[$key]);
            }
        }
        $env['PGPASSWORD'] = $password->reveal();
        $sslMode = (string)($config['ssl_mode'] ?? $config['sslmode'] ?? '');
        $env['PGSSLMODE'] = $sslMode !== '' ? $sslMode : (!empty($config['ssl']) ? 'allow' : 'prefer');
        foreach (
            ['ssl_key' => 'PGSSLKEY', 'ssl_cert' => 'PGSSLCERT', 'ssl_ca' => 'PGSSLROOTCERT'] as $key => $variable
        ) {
            if (!empty($config[$key])) {
                $env[$variable] = (string)$config[$key];
            }
        }

        return $env;
    }
}
