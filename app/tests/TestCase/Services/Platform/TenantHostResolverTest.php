<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Cache\TenantAwareCache;
use App\Services\Platform\TenantHostResolver;
use Cake\Cache\Cache;
use Cake\Cache\Exception\CacheWriteException;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class TenantHostResolverTest extends TestCase
{
    private mixed $platformConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformConfig = ConnectionManager::getConfig('platform');
        $this->clearTenantHostCache();
        $this->configurePlatform();
    }

    protected function tearDown(): void
    {
        $this->clearTenantHostCache();
        ConnectionManager::drop('platform');
        if ($this->platformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->platformConfig);
        }
        parent::tearDown();
    }

    public function testResolveNormalizesHostAndReturnsActiveTenant(): void
    {
        $resolver = new TenantHostResolver();

        $tenant = $resolver->resolve('Alpha.Example.Test.');

        $this->assertNotNull($tenant);
        $this->assertSame('alpha', $tenant->slug);
        $this->assertSame('active', $tenant->status);
    }

    public function testResolveIgnoresInactiveHost(): void
    {
        $resolver = new TenantHostResolver();

        $this->assertNull($resolver->resolve('inactive.example.test'));
    }

    public function testResolveUsesCachedHostMapAfterInitialLoad(): void
    {
        $resolver = new TenantHostResolver();
        $this->assertSame('alpha', $resolver->resolve('alpha.example.test')?->slug);

        ConnectionManager::drop('platform');

        $this->assertSame('alpha', $resolver->resolve('alpha.example.test')?->slug);
    }

    public function testClearCacheForcesFreshHostMap(): void
    {
        $resolver = new TenantHostResolver();
        $this->assertSame('alpha', $resolver->resolve('alpha.example.test')?->slug);

        ConnectionManager::get('platform')->update('tenants', [
            'slug' => 'beta',
            'display_name' => 'Beta',
        ], ['id' => 'tenant-alpha']);

        $this->assertSame('alpha', $resolver->resolve('alpha.example.test')?->slug);
        TenantHostResolver::clearCache();

        $this->assertSame('beta', $resolver->resolve('alpha.example.test')?->slug);
    }

    public function testResolveDoesNotFailWhenCacheWriteFails(): void
    {
        $throwingCache = new class extends TenantAwareCache {
            public function writePlatform(string $key, mixed $value, string $config = 'default'): bool
            {
                throw new CacheWriteException('cache write failed');
            }
        };
        $resolver = new TenantHostResolver('platform', $throwingCache);

        $tenant = $resolver->resolve('alpha.example.test');

        $this->assertNotNull($tenant);
        $this->assertSame('alpha', $tenant->slug);
    }

    private function clearTenantHostCache(): void
    {
        if (in_array('tenant_host_map', Cache::configured(), true)) {
            Cache::clear('tenant_host_map');
        }
    }

    private function configurePlatform(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'cacheMetadata' => false,
        ]);
        $connection = ConnectionManager::get('platform');
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT,
                display_name TEXT,
                status TEXT,
                db_server TEXT,
                db_name TEXT,
                db_role TEXT,
                schema_version TEXT,
                tenant_config TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_hosts (
                id TEXT PRIMARY KEY,
                tenant_id TEXT,
                host_normalized TEXT,
                status TEXT
            )',
        );
        $connection->insert('tenants', [
            'id' => 'tenant-alpha',
            'slug' => 'alpha',
            'display_name' => 'Alpha',
            'status' => 'active',
            'db_server' => 'localhost',
            'db_name' => 'alpha_db',
            'db_role' => 'alpha_role',
            'schema_version' => '20260516000000',
            'tenant_config' => '{}',
        ]);
        $connection->insert('tenant_hosts', [
            'id' => 'host-alpha',
            'tenant_id' => 'tenant-alpha',
            'host_normalized' => 'alpha.example.test',
            'status' => 'active',
        ]);
        $connection->insert('tenant_hosts', [
            'id' => 'host-inactive',
            'tenant_id' => 'tenant-alpha',
            'host_normalized' => 'inactive.example.test',
            'status' => 'inactive',
        ]);
    }
}
