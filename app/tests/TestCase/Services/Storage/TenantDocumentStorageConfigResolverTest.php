<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Storage;

use App\KMP\TenantMetadata;
use App\Services\Storage\TenantDocumentStorageConfigResolver;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class TenantDocumentStorageConfigResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('Documents.storage.azure', [
            'authMode' => 'managedIdentity',
            'accountName' => 'kmpdocs',
            'container' => 'documents',
            'containerPrefix' => 'tenantdocs',
        ]);
    }

    public function testTenantConfigContainerWins(): void
    {
        $tenant = $this->tenant('tenant-a', [
            'documents' => [
                'blob_container' => 'documents-tenant-a',
                'blob_prefix' => 'tenants/tenant-a',
            ],
        ]);

        $resolver = new TenantDocumentStorageConfigResolver();
        $config = $resolver->resolveAzureConfig($tenant);

        $this->assertSame('documents-tenant-a', $config['container']);
        $this->assertSame('tenants/tenant-a', $config['prefix']);
        $this->assertSame('managedIdentity', $config['authMode']);
        $this->assertSame('kmpdocs', $config['accountName']);
    }

    public function testContainerFallsBackToConfiguredPrefixAndTenantSlug(): void
    {
        $resolver = new TenantDocumentStorageConfigResolver();

        $this->assertSame(
            'tenantdocs-tenant-b',
            $resolver->resolveContainerName(Configure::read('Documents.storage.azure'), $this->tenant('tenant-b')),
        );
    }

    public function testContainerFallsBackToDefaultWhenNoTenant(): void
    {
        $resolver = new TenantDocumentStorageConfigResolver();

        $this->assertSame(
            'documents',
            $resolver->resolveContainerName(Configure::read('Documents.storage.azure')),
        );
    }

    public function testInvalidConfiguredContainerFailsFast(): void
    {
        $tenant = $this->tenant('tenant-a', [
            'documents' => [
                'blob_container' => 'Invalid_Container',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new TenantDocumentStorageConfigResolver())->resolveAzureConfig($tenant);
    }

    /**
     * @param array<string, mixed> $tenantConfig Tenant config
     * @return \App\KMP\TenantMetadata
     */
    private function tenant(string $slug, array $tenantConfig = []): TenantMetadata
    {
        return new TenantMetadata(
            $slug . '-id',
            $slug,
            ucfirst($slug),
            'active',
            'db.example.org',
            $slug . '_db',
            $slug . '_role',
            null,
            $tenantConfig,
        );
    }
}
