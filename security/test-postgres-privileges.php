<?php
declare(strict_types=1);

// Disposable-infrastructure test: no application bootstrap or shared test database.
require '/var/www/html/vendor/autoload.php';

use App\Services\Platform\AdministrativeDatabase;
use App\Services\Platform\PostgresRuntimePrivileges;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

Configure::write('Database.adminJob', true);
$config = [
    'className' => Connection::class,
    'driver' => Postgres::class,
    'host' => 'postgres',
    'database' => 'postgres',
    'username' => 'postgres',
    'password' => 'synthetic-disposable-test-password',
    'cacheMetadata' => false,
];
ConnectionManager::setConfig('admin', $config);
$bootstrap = ConnectionManager::get('admin');
$bootstrap->execute("CREATE ROLE schema_owner LOGIN CREATEDB CREATEROLE PASSWORD 'synthetic-schema-owner-password'");
ConnectionManager::drop('admin');
$config['username'] = 'schema_owner';
$config['password'] = 'synthetic-schema-owner-password';
ConnectionManager::setConfig('admin', $config);
$admin = ConnectionManager::get('admin');
foreach (['tenant_a', 'tenant_b', 'platform'] as $database) {
    $admin->execute('CREATE ROLE ' . $database . '_runtime LOGIN PASSWORD \'synthetic-runtime-password-long\'');
    $admin->execute('GRANT ' . $database . '_runtime TO schema_owner WITH SET TRUE');
    $admin->execute('CREATE DATABASE ' . $database . ' OWNER ' . $database . '_runtime');
    $tenantConfig = array_merge($config, ['database' => $database, 'username' => $database . '_runtime',
        'password' => 'synthetic-runtime-password-long']);
    ConnectionManager::setConfig('seed_' . $database, $tenantConfig);
    $seed = ConnectionManager::get('seed_' . $database);
    $seed->execute('CREATE TABLE records (id BIGSERIAL PRIMARY KEY, value TEXT)');
    ConnectionManager::drop('seed_' . $database);
    $admin->execute('GRANT ' . $database . '_runtime TO schema_owner WITH SET FALSE');
}
$reconcile = new PostgresRuntimePrivileges($admin);
$roles = ['tenant_a_runtime', 'tenant_b_runtime', 'platform_runtime'];
$admin->execute('GRANT tenant_b_runtime TO tenant_a_runtime WITH SET TRUE');
for ($pass = 0; $pass < 2; $pass++) {
    foreach (['tenant_a', 'tenant_b', 'platform'] as $database) {
        $reconcile->reconcile($database, $database . '_runtime', null, $roles);
    }
}
$assertions = 0;
$mustDeny = function (callable $callback) use (&$assertions): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), 'SQLSTATE[42501]')
            && !str_contains($exception->getMessage(), 'User does not have CONNECT privilege.')) {
            throw new RuntimeException('Unexpected error while checking a database privilege denial.', 0, $exception);
        }
        $assertions++;

        return;
    }
    throw new RuntimeException('An unauthorized database operation succeeded.');
};
foreach (['tenant_a', 'tenant_b', 'platform'] as $database) {
    ConnectionManager::setConfig('runtime_' . $database, array_merge($config, [
        'database' => $database,
        'username' => $database . '_runtime',
        'password' => 'synthetic-runtime-password-long',
    ]));
    $runtime = ConnectionManager::get('runtime_' . $database);
    $runtime->execute("INSERT INTO records(value) VALUES ('synthetic')");
    $runtime->execute("UPDATE records SET value = 'updated'");
    if ($runtime->execute('SELECT value FROM records')->fetchColumn(0) !== 'updated') {
        throw new RuntimeException('Runtime DML failed.');
    }
    $runtime->execute('DELETE FROM records');
    $assertions += 4;
    foreach ([
        'CREATE TABLE forbidden (id INTEGER)',
        'CREATE TEMP TABLE forbidden (id INTEGER)',
        'ALTER TABLE records ADD COLUMN forbidden TEXT',
        'DROP TABLE records',
        'TRUNCATE records',
        'CREATE SCHEMA forbidden',
        'CREATE DATABASE forbidden',
        'CREATE ROLE forbidden',
    ] as $sql) {
        $mustDeny(fn() => $runtime->execute($sql));
    }
    foreach (array_diff(['tenant_a', 'tenant_b', 'platform'], [$database]) as $other) {
        $name = 'cross_' . $database . '_' . $other;
        ConnectionManager::setConfig($name, array_merge($runtime->config(), ['className' => Connection::class, 'database' => $other]));
        $mustDeny(fn() => ConnectionManager::get($name)->execute('SELECT * FROM records'));
        $mustDeny(fn() => $runtime->execute('SET ROLE ' . $other . '_runtime'));
    }
    // Future migrations remain owned by the administrative identity, with only DML
    // available to the appropriate runtime role through default privileges.
    ConnectionManager::setConfig('migration_' . $database, array_merge($config, ['database' => $database]));
    $migration = ConnectionManager::get('migration_' . $database);
    $migration->execute('CREATE TABLE future_records (id BIGSERIAL PRIMARY KEY, value TEXT)');
    $runtime->execute("INSERT INTO future_records(value) VALUES ('new schema')");
    $assertions++;
    $mustDeny(fn() => $runtime->execute('DROP TABLE future_records'));
}
$admin->execute('CREATE DATABASE fresh_default');
$reconcile->reconcile('fresh_default', 'fresh_runtime', 'synthetic-fresh-role-password-long');
$reconcile->reconcile('fresh_default', 'fresh_runtime', 'synthetic-rotated-role-password-long');
ConnectionManager::setConfig('fresh_runtime', array_merge($config, [
    'database' => 'fresh_default', 'username' => 'fresh_runtime', 'password' => 'synthetic-rotated-role-password-long',
]));
ConnectionManager::get('fresh_runtime')->execute('SELECT 1');
$assertions++;
Configure::write('Database.adminJob', false);
try {
    AdministrativeDatabase::requireJob();
    throw new LogicException('An ordinary process was allowed administrative work.');
} catch (RuntimeException $exception) {
    $assertions++;
}
printf("Disposable PostgreSQL privilege checks passed: %d assertions.\n", $assertions);
