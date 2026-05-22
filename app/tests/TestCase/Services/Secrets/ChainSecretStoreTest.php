<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\ChainSecretStore;
use App\Services\Secrets\EnvVarSecretStore;
use App\Services\Secrets\FileSecretStore;
use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;

class ChainSecretStoreTest extends TestCase
{
    private string $directory;
    private string $path;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/kmp_chain_secret_store_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->path = $this->directory . '/secrets.local.json';
        $this->prefix = 'KMP_CHAIN_SECRET_' . strtoupper(bin2hex(random_bytes(3))) . '_';
    }

    protected function tearDown(): void
    {
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD');
        if (file_exists($this->path)) {
            chmod($this->path, 0600);
            unlink($this->path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testReadPrecedenceAndExplicitWriteTarget(): void
    {
        putenv($this->prefix . 'TENANT_DEMO_DB_PASSWORD=env-secret');
        $fileStore = new FileSecretStore($this->path, 'test');
        $chain = new ChainSecretStore([
            'env' => new EnvVarSecretStore($this->prefix),
            'file' => $fileStore,
        ], 'file');

        $this->assertSame('env-secret', $chain->get('tenant.demo.db.password')?->reveal());
        $chain->put('tenant.demo.db.password', new SensitiveString('file-secret'));

        $this->assertSame('env-secret', $chain->get('tenant.demo.db.password')?->reveal());
        $this->assertSame('file-secret', $fileStore->get('tenant.demo.db.password')?->reveal());
        $this->assertSame(['tenant.demo.db.password'], $chain->list('tenant.demo.'));

        $chain->delete('tenant.demo.db.password');
        $this->assertNull($fileStore->get('tenant.demo.db.password'));
        $this->assertTrue($chain->exists('tenant.demo.db.password'));
    }
}
