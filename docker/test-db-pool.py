#!/usr/bin/env python3
"""Exercise the image's pool against an isolated disposable PostgreSQL server."""
import argparse
import json
import pathlib
import subprocess
import tempfile
import time
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--image', required=True)
parser.add_argument('--app-directory')
args = parser.parse_args()
suffix = uuid.uuid4().hex[:12]
network = 'kmp-pool-test-' + suffix
database = network + '-db'
client = network + '-client'
web = network + '-web'
root = pathlib.Path(__file__).resolve().parent


def docker(*command, check=True):
    return subprocess.run(['docker', *command], check=check, capture_output=True, text=True, timeout=180)


try:
    docker('network', 'create', network)
    docker('run', '-d', '--name', database, '--network', network, '--network-alias', 'pool-db',
           '-e', 'POSTGRES_PASSWORD=disposable-pool-test', 'postgres:16')
    for attempt in range(60):
        if docker('exec', database, 'pg_isready', '-U', 'postgres', check=False).returncode == 0:
            break
        time.sleep(1)
    mounts = ['-v', str(root / 'test-db-pool.php') + ':/opt/docker/test-db-pool.php:ro']
    if args.app_directory:
        mounts += ['-v', str(pathlib.Path(args.app_directory).resolve()) + ':/var/www/html:ro']
    result = docker('run', '--name', client, '--network', network, '--entrypoint', 'php',
                    '-e', 'KMP_POOL_TEST_ALLOWED=true', '-e', 'KMP_POOL_TEST_HOST=pool-db',
                    *mounts, args.image, '/opt/docker/test-db-pool.php', check=False)
    print(result.stdout, end='')
    if result.returncode:
        print(result.stderr)
        raise SystemExit(result.returncode)
    if not args.app_directory:
        # Exercise the final image's real entrypoint and Apache bootstrap too.
        with tempfile.TemporaryDirectory(prefix='kmp-pool-probe-') as temporary:
            probe = pathlib.Path(temporary) / 'pool-contract.php'
            probe.write_text('''<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
$result = [];
foreach (['default', 'platform'] as $name) {
    $connection = \\Cake\\Datasource\\ConnectionManager::get($name);
    $result[$name] = [
        'pooled' => $connection->getDriver() instanceof \\App\\Database\\Driver\\PooledPostgres,
        'row' => $connection->execute('SELECT current_database(), current_user, pg_backend_pid()')->fetch('assoc'),
    ];
}
header('Content-Type: application/json');
echo json_encode($result, JSON_THROW_ON_ERROR);
''')
            probe.chmod(0o644)
            docker('run', '-d', '--name', web, '--network', network,
                   '-e', 'DATABASE_URL=postgres://pool_alpha:rotated-pool-test@pool-db/pool_alpha',
                   '-e', 'PLATFORM_DATABASE_URL=postgres://pool_beta:disposable-pool-test@pool-db/pool_beta',
                   '-e', 'SECURITY_SALT=disposable-pool-web-test-only',
                   '-e', 'KMP_DB_POOL_ENABLED=true', '-e', 'KMP_DB_POOL_DATABASE_SLOTS=2',
                   '-e', 'KMP_DB_POOL_SIZE=2', '-e', 'KMP_TENANCY_ENABLED=false',
                   '-e', 'KMP_SKIP_MIGRATIONS=true', '-e', 'KMP_SKIP_CRON=true',
                   '-e', 'REQUIRE_HTTPS=false',
                   '-v', str(probe) + ':/var/www/html/webroot/pool-contract.php:ro', args.image)
            for attempt in range(60):
                response = docker('exec', web, 'curl', '-fsS', '--max-time', '3',
                                  'http://localhost/pool-contract.php', check=False)
                if response.returncode == 0:
                    break
                time.sleep(1)
            else:
                print(docker('logs', web, check=False).stdout)
                raise RuntimeError('Pooled web startup failed.')
            first = json.loads(response.stdout)
            second = json.loads(docker('exec', web, 'curl', '-fsS',
                                       'http://localhost/pool-contract.php').stdout)
            direct = json.loads(docker('exec', web, 'php',
                                       '/var/www/html/webroot/pool-contract.php').stdout)
            for name, role in [('default', 'pool_alpha'), ('platform', 'pool_beta')]:
                assert first[name]['pooled'] and second[name]['pooled'], 'Web bootstrap bypassed pool.'
                assert not direct[name]['pooled'], 'CLI unexpectedly selected pooled driver.'
                assert first[name]['row']['current_database'] == role, 'Wrong backend database.'
                assert first[name]['row']['current_user'] == role, 'Wrong backend role.'
                assert first[name]['row']['pg_backend_pid'] == second[name]['row']['pg_backend_pid'], 'Web did not reuse backend.'
            print('PASS: production entrypoint, pooled Apache requests, backend reuse, direct CLI.')
finally:
    docker('rm', '-f', web, client, database, check=False)
    docker('network', 'rm', network, check=False)
