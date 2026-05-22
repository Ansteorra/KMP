<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Secrets;

use App\Services\Secrets\DatabaseSecretStore;
use App\Services\Secrets\SensitiveString;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;

class DatabaseSecretStoreTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousPlatformConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testPutGetListRotatedAtAndDelete(): void
    {
        $store = $this->store();

        $store->put('tenant.demo.db.password', new SensitiveString('database-secret-1'));
        $store->put('tenant.demo.mail.password', new SensitiveString('database-secret-2'));

        $this->assertTrue($store->exists('tenant.demo.db.password'));
        $this->assertSame('database-secret-1', $store->get('tenant.demo.db.password')?->reveal());
        $this->assertSame(['tenant.demo.db.password', 'tenant.demo.mail.password'], $store->list('tenant.demo.'));
        $this->assertNotNull($store->rotatedAt('tenant.demo.db.password'));

        $store->delete('tenant.demo.db.password');

        $this->assertFalse($store->exists('tenant.demo.db.password'));
        $this->assertNull($store->get('tenant.demo.db.password'));
        $this->assertSame(['tenant.demo.mail.password'], $store->list('tenant.demo.'));
    }

    public function testCiphertextAndMetadataDoNotContainPlaintext(): void
    {
        $store = $this->store();
        $store->put('tenant.demo.api.token', new SensitiveString('plain-secret-marker'));

        $row = $this->platform()->execute(
            'SELECT key_name, key_version, dek_cipher, dek_nonce, dek_tag, wrapped_dek, cipher, nonce, tag, ciphertext, associated_data_hash FROM platform_secret_values',
        )->fetch('assoc');
        $serializedRow = json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->assertStringNotContainsString('plain-secret-marker', $serializedRow);
        $this->assertNotSame('plain-secret-marker', (string)$row['ciphertext']);
        $this->assertStringStartsWith('base64:', (string)$row['dek_nonce']);
        $this->assertStringStartsWith('base64:', (string)$row['wrapped_dek']);
        $this->assertStringStartsWith('base64:', (string)$row['nonce']);
        $this->assertStringStartsWith('base64:', (string)$row['ciphertext']);
    }

    public function testAssociatedDataTamperingFailsClosed(): void
    {
        $store = $this->store();
        $store->put('tenant.demo.original', new SensitiveString('tamper-proof-secret'));
        $this->platform()->update(
            'platform_secret_values',
            ['name' => 'tenant.demo.tampered'],
            ['name' => 'tenant.demo.original'],
        );

        $this->assertNull($store->get('tenant.demo.original'));
        $this->expectException(RuntimeException::class);
        $store->get('tenant.demo.tampered');
    }

    public function testRefusesSelfWrapAndMissingMasterKey(): void
    {
        $inner = $this->store();

        $this->expectException(InvalidArgumentException::class);
        new DatabaseSecretStore($inner, 'platform.master_kek', 'platform');
    }

    public function testMissingMasterKeyThrows(): void
    {
        $store = new DatabaseSecretStore(new ArraySecretStore([]), 'platform.master_kek', 'platform');

        $this->expectException(RuntimeException::class);
        $store->put('tenant.demo.db.password', new SensitiveString('database-secret-1'));
    }

    private function store(): DatabaseSecretStore
    {
        return new DatabaseSecretStore(
            new ArraySecretStore(['platform.master_kek' => base64_encode(random_bytes(32))]),
            'platform.master_kek',
            'platform',
        );
    }

    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }

    private function createSchema(): void
    {
        $connection = $this->platform();
        $connection->execute(
            'CREATE TABLE tenant_secrets_index (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                name TEXT UNIQUE NOT NULL,
                namespace TEXT NOT NULL,
                driver TEXT NOT NULL,
                purpose TEXT NULL,
                rotated_at TEXT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_secret_keks (
                id TEXT PRIMARY KEY,
                key_name TEXT NOT NULL,
                key_version TEXT NOT NULL,
                master_secret_name TEXT NOT NULL,
                algorithm TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "active",
                metadata TEXT NULL,
                created_at TEXT NOT NULL,
                rotated_at TEXT NULL,
                retired_at TEXT NULL,
                UNIQUE (key_name, key_version)
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_secret_values (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                name TEXT UNIQUE NOT NULL,
                namespace TEXT NOT NULL,
                key_name TEXT NOT NULL,
                key_version TEXT NOT NULL,
                dek_cipher TEXT NOT NULL,
                dek_nonce BLOB NOT NULL,
                dek_tag BLOB NULL,
                wrapped_dek BLOB NOT NULL,
                cipher TEXT NOT NULL,
                nonce BLOB NOT NULL,
                tag BLOB NULL,
                ciphertext BLOB NOT NULL,
                associated_data_hash TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT NOT NULL,
                modified_at TEXT NULL,
                rotated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
    }
}
