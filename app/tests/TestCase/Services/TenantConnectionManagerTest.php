<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\TenantConnectionManager;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use RuntimeException;

class TenantConnectionManagerTest extends TestCase
{
    public function testWithTenantBindsTenantConnectionAndRestoresContext(): void
    {
        $defaultConfig = ConnectionManager::getConfig('default');
        $this->assertIsArray($defaultConfig);
        $password = (string)($defaultConfig['password'] ?? '');
        $tenant = new TenantMetadata(
            'tenant-id',
            'demo',
            'Demo',
            'active',
            (string)$defaultConfig['host'],
            (string)$defaultConfig['database'],
            (string)$defaultConfig['username'],
            '20260516000000',
        );
        $manager = new TenantConnectionManager(new ArraySecretStore([
            'tenant.demo.db.password' => $password,
        ]));

        $result = $manager->withTenant($tenant, function (): string {
            $this->assertSame('demo', TenantContext::slug());
            $connection = ConnectionManager::get(TenantConnectionManager::CONNECTION_ALIAS);

            return (string)$connection->execute('SELECT 1')->fetch()[0];
        });

        $this->assertSame('1', $result);
        $this->assertNull(TenantContext::tryCurrent());
        $this->assertFalse(in_array(TenantConnectionManager::CONNECTION_ALIAS, ConnectionManager::configured(), true));
    }

    public function testMissingPasswordSecretFailsFast(): void
    {
        $manager = new TenantConnectionManager(new ArraySecretStore([]));

        $this->expectException(RuntimeException::class);
        $manager->buildConnectionConfig(new TenantMetadata(
            'tenant-id',
            'demo',
            'Demo',
            'active',
            'db',
            'demo_db',
            'demo_role',
        ));
    }

    public function testWithTenantRoutesDefaultConnectionAndRestoresIt(): void
    {
        $previousDefaultConfig = ConnectionManager::getConfig('default');
        $previousDefaultAlias = ConnectionManager::aliases()['default'] ?? null;
        $this->assertIsArray($previousDefaultConfig);
        $directory = ROOT . DS . 'tmp' . DS . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $baseDatabase = $directory . DS . 'tenant-manager-default.sqlite';
        $tenantDatabase = $directory . DS . 'tenant-manager-tenant.sqlite';
        foreach ([$baseDatabase, $tenantDatabase] as $database) {
            if (file_exists($database)) {
                unlink($database);
            }
        }

        try {
            if ($previousDefaultAlias !== null) {
                ConnectionManager::dropAlias('default');
            }
            ConnectionManager::drop('default');
            ConnectionManager::setConfig('default', [
                'className' => Connection::class,
                'driver' => Sqlite::class,
                'database' => $baseDatabase,
                'host' => 'localhost',
                'username' => 'base',
                'password' => 'base-secret',
                'cacheMetadata' => false,
                'quoteIdentifiers' => false,
            ]);
            ConnectionManager::get('default')->execute('CREATE TABLE base_marker (id INTEGER PRIMARY KEY)');

            $tenant = new TenantMetadata(
                'tenant-id',
                'demo',
                'Demo',
                'active',
                'localhost',
                $tenantDatabase,
                'tenant_user',
            );
            $manager = new TenantConnectionManager(new ArraySecretStore([
                'tenant.demo.db.password' => 'tenant-secret',
            ]));

            $manager->withTenant($tenant, function () use ($tenantDatabase): void {
                $this->assertSame($tenantDatabase, ConnectionManager::get('default')->config()['database'] ?? null);
                ConnectionManager::get('default')->execute('CREATE TABLE tenant_marker (id INTEGER PRIMARY KEY)');
                $this->assertTrue(in_array(
                    'tenant_marker',
                    ConnectionManager::get(TenantConnectionManager::CONNECTION_ALIAS)->getSchemaCollection()->listTables(),
                    true,
                ));
            });

            $this->assertSame($baseDatabase, ConnectionManager::getConfig('default')['database'] ?? null);
            $this->assertFalse(in_array(
                'tenant_marker',
                ConnectionManager::get('default')->getSchemaCollection()->listTables(),
                true,
            ));
        } finally {
            if (array_key_exists('default', ConnectionManager::aliases())) {
                ConnectionManager::dropAlias('default');
            }
            ConnectionManager::drop('default');
            if ($previousDefaultAlias !== null) {
                ConnectionManager::alias($previousDefaultAlias, 'default');
            } else {
                ConnectionManager::setConfig('default', $previousDefaultConfig);
            }
            foreach ([$baseDatabase, $tenantDatabase] as $database) {
                if (file_exists($database)) {
                    unlink($database);
                }
            }
        }
    }
}
