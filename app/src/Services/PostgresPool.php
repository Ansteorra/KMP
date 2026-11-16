<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

/** Builds private PgBouncer configuration with a fixed database-slot budget. */
final class PostgresPool
{
    /** Stable aliases keep equal database names on different servers separate. */
    public static function alias(#[SensitiveParameter] array $config): string
    {
        return 'kmp_' . substr(hash('sha256', json_encode([
            strtolower((string)$config['host']), (int)($config['port'] ?? 5432), (string)$config['database'],
        ], JSON_THROW_ON_ERROR)), 0, 32);
    }

    /** Fingerprints never replace PostgreSQL/PgBouncer credential authentication. */
    public static function credential(#[SensitiveParameter] array $config): string
    {
        return hash('sha256', (string)$config['username'] . "\0" . (string)$config['password']);
    }

    /**
     * Retain allocated slots until container replacement, including removed databases.
     * This prevents a refresh from exceeding its budget while old sessions drain.
     *
     * @return array{ini:string,auth:string,hba:string,manifest:array}
     */
    public static function build(
        #[SensitiveParameter] array $connections,
        string $directory,
        int $slots,
        int $size,
        array $previous = [],
    ): array {
        if ($slots < 1 || $slots > 64 || $size < 1 || $size > 16 || $slots * $size > 128) {
            throw new InvalidArgumentException('Invalid PostgreSQL pool connection budget.');
        }
        if (!preg_match('#^/[a-zA-Z0-9_/-]+$#D', $directory)) {
            throw new InvalidArgumentException('Invalid PostgreSQL pool directory.');
        }
        $routes = $previous['routes'] ?? [];
        foreach ($routes as &$route) {
            $route['credentials'] = [];
        }
        unset($route);
        $users = [];
        $tls = null;
        foreach ($connections as $config) {
            foreach (['host', 'database', 'username', 'password'] as $key) {
                if (
                    !is_string($config[$key] ?? null) || $config[$key] === ''
                    || preg_match('/[\x00-\x1f\x7f]/', $config[$key])
                ) {
                    throw new InvalidArgumentException('Invalid PostgreSQL pool connection input.');
                }
            }
            $encrypted = filter_var($config['ssl'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($tls !== null && $tls !== $encrypted) {
                throw new InvalidArgumentException('A pool cannot mix TLS and non-TLS database servers.');
            }
            if (!empty($config['ssl_ca']) || !empty($config['ssl_cert']) || !empty($config['ssl_key'])) {
                throw new InvalidArgumentException('Custom database TLS certificates need a separate pool.');
            }
            $tls = $encrypted;
            $username = $config['username'];
            if (isset($users[$username]) && !hash_equals($users[$username], $config['password'])) {
                throw new InvalidArgumentException('Pool usernames require consistent credentials across servers.');
            }
            $users[$username] = $config['password'];
            $alias = self::alias($config);
            $routes[$alias] ??= [
                'host' => $config['host'], 'port' => (int)($config['port'] ?? 5432),
                'database' => $config['database'], 'credentials' => [],
            ];
            $routes[$alias]['credentials'][$username] = self::credential($config);
        }
        if ($routes === [] || count($routes) > $slots) {
            throw new RuntimeException('PostgreSQL pool database-slot budget exceeded; resize the web pool.');
        }
        ksort($routes);
        ksort($users);
        $ini = "[databases]\n";
        $hba = '';
        foreach ($routes as $alias => $route) {
            $ini .= sprintf(
                "%s = host=%s port=%d dbname=%s pool_size=%d max_db_connections=%d\n",
                $alias,
                self::connectionValue($route['host']),
                $route['port'],
                self::connectionValue($route['database']),
                $size,
                $size,
            );
            foreach ($route['credentials'] as $user => $fingerprint) {
                $hba .= sprintf("local %s %s scram-sha-256\n", $alias, self::quoted($user));
            }
        }
        $hba .= "local all all reject\n";
        $ini .= "\n[pgbouncer]\nlisten_addr =\nlisten_port = 6432\n"
            . "unix_socket_dir = $directory\nunix_socket_mode = 0600\n"
            . "auth_type = hba\nauth_file = $directory/users.txt\nauth_hba_file = $directory/pg_hba.conf\n"
            . "pool_mode = session\ndefault_pool_size = $size\nreserve_pool_size = 0\n"
            . "max_client_conn = 128\nserver_reset_query = DISCARD ALL\n"
            . "server_idle_timeout = 60\nserver_lifetime = 600\n"
            . "server_connect_timeout = 5\nserver_login_retry = 1\nquery_wait_timeout = 15\n"
            . 'server_tls_sslmode = ' . ($tls ? 'verify-full' : 'disable') . "\n"
            . ($tls ? "server_tls_ca_file = /etc/ssl/certs/ca-certificates.crt\n" : '')
            . "log_connections = 0\nlog_disconnections = 0\n";
        $auth = '';
        foreach ($users as $user => $password) {
            $auth .= self::quoted($user) . ' ' . self::quoted($password) . "\n";
        }

        return ['ini' => $ini, 'auth' => $auth, 'hba' => $hba, 'manifest' => ['routes' => $routes]];
    }

    /** Refuse stale credentials and unknown routes instead of silently bypassing the pool. */
    public static function dsn(#[SensitiveParameter] array $config, string $directory): string
    {
        $path = $directory . '/routes.json';
        $raw = is_readable($path) ? file_get_contents($path) : false;
        $manifest = $raw === false ? null : json_decode($raw, true);
        $alias = self::alias($config);
        $expected = $manifest['routes'][$alias]['credentials'][$config['username']] ?? null;
        if (!is_string($expected) || !hash_equals($expected, self::credential($config))) {
            throw new RuntimeException('PostgreSQL pool route or credentials are awaiting refresh.');
        }

        return "pgsql:host=$directory;port=6432;dbname=$alias";
    }

    /** Quote a PgBouncer backend connection-string value. */
    private static function connectionValue(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /** Quote an authentication-file or HBA identifier. */
    private static function quoted(#[SensitiveParameter] string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
