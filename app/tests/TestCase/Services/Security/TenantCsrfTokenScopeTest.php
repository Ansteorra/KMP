<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Security\TenantCsrfTokenScope;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;

class TenantCsrfTokenScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        unset($_ENV['KMP_TENANT_CSRF_ENABLED'], $_SERVER['KMP_TENANT_CSRF_ENABLED']);
        putenv('KMP_TENANT_CSRF_ENABLED');
    }

    protected function tearDown(): void
    {
        unset($_ENV['KMP_TENANT_CSRF_ENABLED'], $_SERVER['KMP_TENANT_CSRF_ENABLED']);
        putenv('KMP_TENANT_CSRF_ENABLED');

        parent::tearDown();
    }

    public function testDefaultDisabledScopePreservesGlobalSalt(): void
    {
        $scope = new TenantCsrfTokenScope();
        $tenant = new TenantMetadata('tenant-a-id', 'tenant-a', 'Tenant A', 'active', 'db', 'tenant_a', 'role');

        $this->assertFalse($scope->isEnabled());
        TenantContext::with($tenant, function () use ($scope): void {
            $this->assertSame(Security::getSalt(), $scope->signingSalt());
        });
    }

    public function testEnabledScopeAddsTenantBindingWhenContextExists(): void
    {
        $_ENV['KMP_TENANT_CSRF_ENABLED'] = 'true';
        $scope = new TenantCsrfTokenScope();
        $tenant = new TenantMetadata('tenant-a-id', 'tenant-a', 'Tenant A', 'active', 'db', 'tenant_a', 'role');

        $this->assertTrue($scope->isEnabled());
        TenantContext::with($tenant, function () use ($scope): void {
            $this->assertNotSame(Security::getSalt(), $scope->signingSalt());
            $this->assertSame($scope->signingSalt(), $scope->signingSalt());
        });
    }
}
