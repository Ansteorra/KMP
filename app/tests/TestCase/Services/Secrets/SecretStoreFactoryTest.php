<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\ChainSecretStore;
use App\Services\Secrets\DatabaseSecretStore;
use App\Services\Secrets\SecretStoreFactory;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class SecretStoreFactoryTest extends TestCase
{
    public function testBuildsConfiguredChainStore(): void
    {
        $store = SecretStoreFactory::fromConfig([
            'driver' => 'chain',
            'drivers' => [
                'chain' => [
                    'drivers' => ['env', 'file'],
                    'writeTo' => 'file',
                ],
                'env' => ['prefix' => 'KMP_FACTORY_SECRET_'],
                'file' => [
                    'path' => sys_get_temp_dir() . '/kmp_factory_secrets_' . bin2hex(random_bytes(6)) . '.json',
                    'environment' => 'test',
                    'allowInEnvironments' => ['test'],
                ],
            ],
        ]);

        $this->assertInstanceOf(ChainSecretStore::class, $store);
    }

    public function testBuildsDatabaseStoreWithExternalMasterDriver(): void
    {
        $store = SecretStoreFactory::fromConfig([
            'driver' => 'database',
            'drivers' => [
                'database' => [
                    'masterDriver' => 'env',
                    'masterKeyName' => 'platform.master_kek',
                ],
                'env' => ['prefix' => 'KMP_FACTORY_SECRET_'],
            ],
        ]);

        $this->assertInstanceOf(DatabaseSecretStore::class, $store);
    }

    public function testDatabaseStoreRefusesSelfWrappingMasterDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SecretStoreFactory::fromConfig([
            'driver' => 'database',
            'drivers' => [
                'database' => [
                    'masterDriver' => 'database',
                    'masterKeyName' => 'platform.master_kek',
                ],
            ],
        ]);
    }
}
