<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Cache;

use App\KMP\MissingTenantContextException;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Cache\TenantAwareCache;
use Cake\Cache\Cache;
use Cake\Cache\Engine\ArrayEngine;
use Cake\TestSuite\TestCase;

class TenantAwareCacheTest extends TestCase
{
    private const CONFIG = 'tenant_aware_cache_test';

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array(self::CONFIG, Cache::configured(), true)) {
            Cache::setConfig(self::CONFIG, [
                'className' => ArrayEngine::class,
                'duration' => '+1 hour',
            ]);
        }
        Cache::clear(self::CONFIG);
    }

    protected function tearDown(): void
    {
        Cache::clear(self::CONFIG);
        parent::tearDown();
    }

    public function testTenantKeysCannotCollide(): void
    {
        $cache = new TenantAwareCache();
        $tenantA = $this->tenant('tenant-a', 'tenant-a-id');
        $tenantB = $this->tenant('tenant-b', 'tenant-b-id');

        TenantContext::with($tenantA, function () use ($cache): void {
            $this->assertTrue($cache->write('dashboard', 'value-a', self::CONFIG));
        });
        TenantContext::with($tenantB, function () use ($cache): void {
            $this->assertTrue($cache->write('dashboard', 'value-b', self::CONFIG));
        });

        TenantContext::with($tenantA, function () use ($cache): void {
            $this->assertSame('value-a', $cache->read('dashboard', self::CONFIG));
        });
        TenantContext::with($tenantB, function () use ($cache): void {
            $this->assertSame('value-b', $cache->read('dashboard', self::CONFIG));
        });
    }

    public function testPlatformScopeStaysSharedAcrossTenants(): void
    {
        $cache = new TenantAwareCache();
        $tenantA = $this->tenant('tenant-a', 'tenant-a-id');
        $tenantB = $this->tenant('tenant-b', 'tenant-b-id');

        TenantContext::with($tenantA, function () use ($cache): void {
            $this->assertTrue($cache->writePlatform('feature-flags', ['shared' => true], self::CONFIG));
        });

        TenantContext::with($tenantB, function () use ($cache): void {
            $this->assertSame(['shared' => true], $cache->readPlatform('feature-flags', self::CONFIG));
        });
    }

    public function testMissingTenantContextPreservesSingleTenantKeys(): void
    {
        $cache = new TenantAwareCache();

        $this->assertTrue($cache->write('legacy-key', 'legacy-value', self::CONFIG));
        $this->assertSame('legacy-value', Cache::read('legacy-key', self::CONFIG));
    }

    public function testRequireTenantContextFailsFast(): void
    {
        $cache = new TenantAwareCache(true);

        $this->expectException(MissingTenantContextException::class);
        $cache->tenantKey('requires-tenant');
    }

    public function testStaticTenantScopedKeyMatchesInstanceKey(): void
    {
        $cache = new TenantAwareCache();
        $tenant = $this->tenant('tenant-a', 'tenant-a-id');

        TenantContext::with($tenant, function () use ($cache): void {
            $this->assertSame(
                $cache->tenantKey('logical-key'),
                TenantAwareCache::tenantScopedKey('logical-key'),
            );
        });
    }

    private function tenant(string $slug, string $id): TenantMetadata
    {
        return new TenantMetadata($id, $slug, ucfirst($slug), 'active', 'db', $slug . '_db', $slug . '_role');
    }
}
