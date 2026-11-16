<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\PublicAssetService;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use League\Flysystem\FilesystemOperator;
use RuntimeException;

class PublicAssetServiceTest extends BaseTestCase
{
    public function testRemotePublicationUsesImmutableHeadersAndTenantNamespaces(): void
    {
        Configure::write('PublicAssets.adapter', 'azure');
        Configure::write('PublicAssets.baseUrl', 'https://art.example.test/public');
        $bytes = "\x89PNG\r\n\x1a\nnormalized fixture bytes";
        $written = [];
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturnCallback(
            function ($key) use (&$written): bool {
                return isset($written[$key]);
            },
        );
        $filesystem->expects($this->exactly(2))->method('write')->willReturnCallback(
            function ($key, $contents, $options) use (&$written, $bytes): void {
                $this->assertSame($bytes, $contents);
                $this->assertSame('image/png', $options['httpHeaders']['contentType']);
                $this->assertSame(PublicAssetService::CACHE_CONTROL, $options['httpHeaders']['cacheControl']);
                $written[$key] = $contents;
            },
        );
        $service = new PublicAssetService($filesystem);
        $tenant = static fn($id) => new TenantMetadata($id, $id, $id, 'active', 'synthetic', 'synthetic', 'synthetic');
        $one = TenantContext::with($tenant('tenant-one'), fn() => $service->publishPng($bytes));
        $again = TenantContext::with($tenant('tenant-one'), fn() => $service->publishPng($bytes));
        $two = TenantContext::with($tenant('tenant-two'), fn() => $service->publishPng($bytes));

        $this->assertSame($one, $again);
        $this->assertNotSame($one, $two);
        $this->assertSame('https://art.example.test/public/' . $one, PublicAssetService::url($one));
    }

    public function testUrlRejectsUntrustedPaths(): void
    {
        $this->expectException(RuntimeException::class);
        PublicAssetService::url('../private-document.png');
    }

    public function testRemoteUrlRequiresExplicitPublicOrigin(): void
    {
        Configure::write('PublicAssets.adapter', 'azure');
        Configure::write('PublicAssets.baseUrl', '');
        $this->expectException(RuntimeException::class);
        PublicAssetService::url('images/' . str_repeat('a', 32) . '/' . str_repeat('b', 64) . '.png');
    }
}
