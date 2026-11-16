<?php
declare(strict_types=1);

// Disposable-container contract test; never run against application databases.
require '/var/www/html/vendor/autoload.php';

use App\Database\Driver\PooledPostgres;
use App\Services\PostgresPool;

if (getenv('KMP_POOL_TEST_ALLOWED') !== 'true' || getenv('KMP_POOL_TEST_HOST') !== 'pool-db') {
    throw new RuntimeException('Disposable pool test guard failed.');
}
$password = 'disposable-pool-test';
$admin = new PDO('pgsql:host=pool-db;dbname=postgres', 'postgres', $password);
foreach (['alpha', 'beta'] as $tenant) {
    $admin->exec("CREATE ROLE pool_$tenant LOGIN PASSWORD '$password'");
    $admin->exec("CREATE DATABASE pool_$tenant OWNER pool_$tenant");
    $admin->exec("REVOKE ALL ON DATABASE pool_$tenant FROM PUBLIC");
}
$directory = '/run/kmp-db-pool';
mkdir($directory, 0700, true);
chown($directory, 'www-data');
$a = ['host' => 'pool-db', 'port' => 5432, 'database' => 'pool_alpha',
    'username' => 'pool_alpha', 'password' => $password, 'ssl' => false, 'timezone' => 'UTC'];
$b = array_replace($a, ['database' => 'pool_beta', 'username' => 'pool_beta']);
$publish = static function (array $configs, array $old = []) use ($directory): array {
    $built = PostgresPool::build($configs, $directory, 2, 2, $old);
    foreach (['users.txt' => $built['auth'], 'pg_hba.conf' => $built['hba'],
        'pgbouncer.ini' => $built['ini'], 'routes.json' => json_encode($built['manifest'])] as $name => $body) {
        file_put_contents("$directory/$name", $body);
        chmod("$directory/$name", 0600);
        chown("$directory/$name", 'www-data');
    }

    return $built['manifest'];
};
$manifest = $publish([$a, $b]);
$process = proc_open(['/usr/sbin/pgbouncer', '-u', 'www-data', "$directory/pgbouncer.ini"],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/tmp/pool-test.log', 'a'],
        2 => ['file', '/tmp/pool-test.log', 'a']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Pool process failed to start.');
}
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$connect = static function (array $config) use ($directory): PooledPostgres {
    $driver = new PooledPostgres($config + ['poolDirectory' => $directory, 'persistent' => false]);
    $driver->connect();

    return $driver;
};
$scalar = static fn(PooledPostgres $driver, string $sql): mixed => $driver->execute($sql)->fetch('num')[0];
try {
    for ($attempt = 0; $attempt < 50 && !file_exists("$directory/.s.PGSQL.6432"); $attempt++) {
        usleep(100000);
    }
    $one = $connect($a);
    $pid = $scalar($one, 'SELECT pg_backend_pid()');
    $one->execute('CREATE TEMP TABLE pool_session_marker (id int)');
    $one->disconnect();
    unset($one);
    usleep(100000);
    $two = $connect($a);
    $assert($scalar($two, 'SELECT pg_backend_pid()') === $pid, 'Backend was not reused.');
    $assert($scalar($two, "SELECT to_regclass('pg_temp.pool_session_marker')") === null, 'Temporary state leaked.');
    $assert($scalar($two, 'SELECT current_database()') === 'pool_alpha', 'Alpha route selected the wrong database.');
    $two->disconnect();
    unset($two);
    $other = $connect($b);
    $assert($scalar($other, 'SELECT current_database()') === 'pool_beta', 'Beta route selected the wrong database.');
    $assert($scalar($other, 'SELECT current_user') === 'pool_beta', 'Tenant database role was replaced.');
    $other->disconnect();
    unset($other);

    // Bypass the PHP route check: the pool's own HBA must reject a valid user on another tenant's route.
    $rejected = false;
    try {
        new PDO(PostgresPool::dsn($a, $directory), $b['username'], $password);
    } catch (PDOException) {
        $rejected = true;
    }
    $assert($rejected, 'Pool accepted cross-tenant credentials.');

    // Two active sessions exhaust the per-database cap. A third must wait, not open another backend.
    $first = $connect($a);
    $second = $connect($a);
    $dsn = PostgresPool::dsn($a, $directory);
    $clientCode = '$c=json_decode(stream_get_contents(STDIN),true);$p=new PDO($c[0],$c[1],$c[2]);$p->query("SELECT 1")->fetchColumn();echo "connected";';
    $waiting = proc_open(['php', '-r', $clientCode], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $clientPipes);
    fwrite($clientPipes[0], json_encode([$dsn, $a['username'], $password]));
    fclose($clientPipes[0]);
    usleep(300000);
    $assert(proc_get_status($waiting)['running'], 'Third session did not wait at the pool limit.');
    $count = $admin->query("SELECT count(*) FROM pg_stat_activity WHERE datname='pool_alpha'")->fetchColumn();
    $assert((int)$count === 2, 'Pool exceeded its two-backend database cap.');
    $first->disconnect();
    unset($first);
    $assert(stream_get_contents($clientPipes[1]) === 'connected', 'Queued client did not acquire the released connection.');
    fclose($clientPipes[1]);
    fclose($clientPipes[2]);
    proc_close($waiting);
    $second->disconnect();
    unset($second);

    $dirty = $connect($a);
    $dirty->execute('CREATE TABLE transaction_marker (id int)');
    $dirty->execute('BEGIN');
    $dirty->execute('INSERT INTO transaction_marker VALUES (1)');
    $dirty->disconnect();
    unset($dirty);
    $clean = $connect($a);
    $assert((int)$scalar($clean, 'SELECT count(*) FROM transaction_marker') === 0, 'Uncommitted data survived disconnect.');
    $clean->disconnect();
    unset($clean);

    $admin->exec("ALTER ROLE pool_alpha PASSWORD 'rotated-pool-test'");
    $rotated = array_replace($a, ['password' => 'rotated-pool-test']);
    $publish([$rotated, $b], $manifest);
    proc_terminate($process, 1); // POSIX SIGHUP, without requiring the optional pcntl extension.
    usleep(200000);
    $newLogin = $connect($rotated);
    $assert($scalar($newLogin, 'SELECT current_user') === 'pool_alpha', 'Rotated credentials failed.');
    $newLogin->disconnect();
    unset($newLogin);
    $admin->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='pool_alpha'");
    usleep(300000);
    $coldRotated = $connect($rotated);
    $assert($scalar($coldRotated, 'SELECT current_user') === 'pool_alpha', 'Rotated upstream credentials failed.');
    $coldRotated->disconnect();
    unset($coldRotated);
    $rejected = false;
    try {
        new PDO($dsn, $a['username'], $password);
    } catch (PDOException) {
        $rejected = true;
    }
    $assert($rejected, 'Old password survived pool authentication reload.');
    echo "PASS: backend reuse, transaction/temp cleanup, tenant roles/HBA isolation, bounded waiting, credential rotation.\n";
} finally {
    proc_terminate($process, 9);
    proc_close($process);
}
