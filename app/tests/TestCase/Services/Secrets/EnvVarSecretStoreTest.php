<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\EnvVarSecretStore;
use BadMethodCallException;
use Cake\TestSuite\TestCase;

class EnvVarSecretStoreTest extends TestCase
{
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'KMP_TEST_SECRET_' . strtoupper(bin2hex(random_bytes(3))) . '_';
    }

    protected function tearDown(): void
    {
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD');
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD_ROTATED_AT');
        parent::tearDown();
    }

    public function testReadsSecretFromEnvironment(): void
    {
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD=env-secret');
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD_ROTATED_AT=2026-01-02T03:04:05+00:00');
        $store = new EnvVarSecretStore($this->prefix);

        $this->assertTrue($store->exists('tenant.demo.db.password'));
        $this->assertSame('env-secret', $store->get('tenant.demo.db.password')?->reveal());
        $this->assertSame(['tenant.demo.db.password'], $store->list('tenant.demo.'));
        $this->assertSame('2026-01-02T03:04:05+00:00', $store->rotatedAt('tenant.demo.db.password')?->format('c'));
    }

    public function testWriteOperationsThrow(): void
    {
        $store = new EnvVarSecretStore($this->prefix);

        $this->expectException(BadMethodCallException::class);
        $store->delete('tenant.demo.db.password');
    }
}
