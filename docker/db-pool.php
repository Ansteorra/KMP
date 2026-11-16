<?php
declare(strict_types=1);

// Private runtime configuration only. Do not print DSNs, credentials, or exception messages.
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/config/bootstrap.php';

use App\Database\Driver\PooledPostgres;
use App\KMP\TenantMetadata;
use App\Services\PostgresPool;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\TenantConnectionManager;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

try {
    $directory = (string)Configure::read('DatabasePool.directory');
    if (!Configure::read('DatabasePool.enabled') || getenv('KMP_ADMIN_JOB') === 'true') {
        throw new RuntimeException('Pool runtime is not enabled.');
    }
    $platform = ConnectionManager::get('platform');
    if (($argv[1] ?? '') === 'check') {
        $driver = new PooledPostgres($platform->config() + ['poolDirectory' => $directory]);
        $driver->execute('SELECT 1')->fetch('assoc');
        exit(0);
    }
    umask(0077);
    $lock = fopen($directory . '/refresh.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Cannot lock pool configuration.');
    }
    $configs = [$platform->config()];
    $default = ConnectionManager::get('default')->config();
    if (filter_var(getenv('KMP_TENANCY_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN)) {
        $manager = new TenantConnectionManager(SecretStoreFactory::fromConfig());
        $platform->begin();
        try {
            $platform->execute('SET TRANSACTION READ ONLY');
            foreach ($platform->execute("SELECT * FROM tenants WHERE status IN ('active', 'suspended')")
                ->fetchAll('assoc') as $row) {
                $tenant = $manager->buildConnectionConfig(TenantMetadata::fromPlatformRow($row));
                // Canonical rotated tenant credentials supersede a duplicate environment default.
                if (PostgresPool::alias($tenant) === PostgresPool::alias($default)
                    && $tenant['username'] === $default['username']) {
                    $default = $tenant;
                }
                $configs[] = $tenant;
            }
        } finally {
            $platform->rollback();
        }
    }
    $configs[] = $default;
    $old = is_file($directory . '/routes.json')
        ? json_decode((string)file_get_contents($directory . '/routes.json'), true, 512, JSON_THROW_ON_ERROR) : [];
    $built = PostgresPool::build(
        $configs,
        $directory,
        (int)Configure::read('DatabasePool.databaseSlots'),
        (int)Configure::read('DatabasePool.connectionsPerDatabase'),
        $old,
    );
    $files = [
        'users.txt' => $built['auth'], 'pg_hba.conf' => $built['hba'], 'pgbouncer.ini' => $built['ini'],
        'routes.json' => json_encode($built['manifest'], JSON_THROW_ON_ERROR),
    ];
    $changed = false;
    foreach ($files as $name => $body) {
        $path = $directory . '/' . $name;
        if (is_file($path) && file_get_contents($path) === $body) {
            continue;
        }
        $temporary = tempnam($directory, 'pool-');
        if ($temporary === false || file_put_contents($temporary, $body) !== strlen($body)
            || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            throw new RuntimeException('Cannot publish private pool configuration.');
        }
        $changed = true;
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    echo $changed ? "changed\n" : "unchanged\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'PostgreSQL pool configuration/check failed (' . $exception::class . ").\n");
    exit(1);
}
