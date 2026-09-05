<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Storage;

use App\Services\Storage\AzureBlobClientFactory;
use App\Test\TestCase\BaseTestCase;
use RuntimeException;

class AzureBlobClientFactoryTest extends BaseTestCase
{
    public function testManagedIdentityDiscardsStaleConnectionString(): void
    {
        $config = AzureBlobClientFactory::normalize([
            'authMode' => 'managedIdentity',
            'accountName' => 'syntheticstorage',
            'connectionString' => 'synthetic-stale-credential',
        ]);
        $this->assertSame('managedIdentity', $config['authMode']);
        $this->assertArrayNotHasKey('connectionString', $config);
    }

    public function testMissingIdentityAccountDoesNotFallBackToConnectionString(): void
    {
        $this->expectException(RuntimeException::class);
        AzureBlobClientFactory::normalize([
            'authMode' => 'managedIdentity',
            'connectionString' => 'synthetic-stale-credential',
        ]);
    }

    public function testConnectionStringModeDoesNotFallBackToIdentity(): void
    {
        $this->expectException(RuntimeException::class);
        AzureBlobClientFactory::normalize(['authMode' => 'connectionString', 'accountName' => 'syntheticstorage']);
    }

    public function testUnknownModeFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        AzureBlobClientFactory::normalize(['authMode' => 'managedIdentitY', 'accountName' => 'syntheticstorage']);
    }
}
