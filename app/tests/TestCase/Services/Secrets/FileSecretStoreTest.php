<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\FileSecretStore;
use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;
use RuntimeException;

class FileSecretStoreTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/kmp_secret_store_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->path = $this->directory . '/secrets.local.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->path)) {
            chmod($this->path, 0600);
            unlink($this->path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testPutGetListRotatedAtAndDelete(): void
    {
        $store = new FileSecretStore($this->path, 'test');

        $this->assertFalse($store->exists('tenant.demo.db.password'));
        $store->put('tenant.demo.db.password', new SensitiveString('pw-1'));
        $store->put('tenant.demo.mail.password', new SensitiveString('pw-2'));

        $this->assertTrue($store->exists('tenant.demo.db.password'));
        $this->assertSame('pw-1', $store->get('tenant.demo.db.password')?->reveal());
        $this->assertSame(['tenant.demo.db.password', 'tenant.demo.mail.password'], $store->list('tenant.demo.'));
        $this->assertNotNull($store->rotatedAt('tenant.demo.db.password'));

        $contents = (string)file_get_contents($this->path);
        $this->assertStringContainsString('tenant.demo.db.password', $contents);
        $this->assertStringContainsString('pw-1', $contents);
        $this->assertSame(0, fileperms($this->path) & 0077);

        $store->delete('tenant.demo.db.password');
        $this->assertFalse($store->exists('tenant.demo.db.password'));
        $this->assertNull($store->get('tenant.demo.db.password'));
    }

    public function testRefusesDisallowedEnvironment(): void
    {
        $this->expectException(RuntimeException::class);
        new FileSecretStore($this->path, 'production');
    }

    public function testRefusesReadableSecretsFile(): void
    {
        file_put_contents($this->path, '{"secrets":{}}');
        chmod($this->path, 0644);
        $store = new FileSecretStore($this->path, 'test');

        $this->expectException(RuntimeException::class);
        $store->list();
    }
}
