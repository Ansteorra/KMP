<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class PlatformSecretsImportEnvCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private const ENV_PREFIX = 'KMP_TRANSITION_TEST_SECRET_';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousPlatformConfig;

    /**
     * @var array<string, mixed>
     */
    private array $previousSecretsConfig;

    /**
     * @var list<string>
     */
    private array $environmentNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        $this->previousSecretsConfig = (array)Configure::read('Secrets', []);
        Configure::write('Secrets', [
            'driver' => 'env',
            'drivers' => [
                'env' => ['prefix' => self::ENV_PREFIX],
                'database' => [
                    'connection' => 'platform',
                    'namespace' => 'platform',
                    'masterDriver' => 'env',
                    'masterKeyName' => 'platform.master_kek',
                    'keyName' => 'platform-secrets',
                    'keyVersion' => 'v1',
                ],
            ],
        ]);
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ($this->environmentNames as $name) {
            putenv($name);
        }
        Configure::write('Secrets', $this->previousSecretsConfig);
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testImportsExactLegacyReferencesWithoutOverwritingDatabaseValues(): void
    {
        $userId = '11111111-2222-4333-8444-555555555555';
        $totpName = sprintf('platform.admin.%s.totp', $userId);
        $this->platform()->insert('tenants', [
            'slug' => 'ansteorra',
            'status' => 'active',
            'tenant_config' => json_encode([
                'email' => [
                    'smtp_password_secret_ref' => 'tenant.ansteorra.smtp-password',
                    'api_secret_ref' => 'tenant.ansteorra.db-password',
                    'connection_string_secret_ref' => 'tenant.ansteorra.empty-secret',
                    'nested' => ['deleted_secret_ref' => 'tenant.ansteorra.deleted-secret'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->platform()->insert('platform_users', ['id' => $userId, 'totp_secret_ref' => $totpName]);
        $this->setSecret('platform.master_kek', base64_encode(random_bytes(32)));
        $this->setSecret('tenant.ansteorra.db.password', 'legacy-database-password');
        $this->setSecret('tenant.ansteorra.kek', 'stale-legacy-tenant-kek');
        $this->setSecret('tenant.ansteorra.smtp-password', 'legacy-smtp-password');
        $this->setSecret('tenant.ansteorra.empty-secret', '');
        $this->setSecret('tenant.ansteorra.deleted-secret', 'stale-deleted-secret');
        $this->setSecret($totpName, 'legacy-platform-totp');

        $database = SecretStoreFactory::fromDriver('database');
        $this->assertInstanceOf(WritableSecretStoreInterface::class, $database);
        $database->put('tenant.ansteorra.kek', new SensitiveString('rotated-database-tenant-kek'));
        $database->put('tenant.ansteorra.deleted-secret', new SensitiveString('deleted-database-secret'));
        $database->delete('tenant.ansteorra.deleted-secret');

        $this->exec('platform secrets import-env');

        $this->assertExitSuccess();
        $this->assertOutputContains('4 imported, 2 already present, 2 not set');
        $this->assertSame(
            'legacy-database-password',
            $database->get('tenant.ansteorra.db.password')?->reveal(),
        );
        $this->assertSame('rotated-database-tenant-kek', $database->get('tenant.ansteorra.kek')?->reveal());
        $this->assertSame('legacy-smtp-password', $database->get('tenant.ansteorra.smtp-password')?->reveal());
        $this->assertSame('legacy-platform-totp', $database->get($totpName)?->reveal());
        $this->assertSame(
            'legacy-database-password',
            $database->get('tenant.ansteorra.db-password')?->reveal(),
        );
        $this->assertNull($database->get('tenant.ansteorra.empty-secret'));
        $this->assertNull($database->get('tenant.ansteorra.deleted-secret'));
        $this->assertSame([
            $totpName,
            'tenant.ansteorra.db-password',
            'tenant.ansteorra.db.password',
            'tenant.ansteorra.kek',
            'tenant.ansteorra.smtp-password',
        ], $database->list());
        foreach (
            [
                'legacy-database-password',
                'stale-legacy-tenant-kek',
                'rotated-database-tenant-kek',
                'legacy-smtp-password',
                'legacy-platform-totp',
                'stale-deleted-secret',
                'deleted-database-secret',
            ] as $secretValue
        ) {
            $this->assertOutputNotContains($secretValue);
        }

        $this->exec('platform secrets import-env');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 imported, 6 already present, 2 not set');
    }

    public function testDefersImportWhenLegacyEnvDriverHasNoDatabaseMasterKey(): void
    {
        $this->platform()->insert('tenants', ['slug' => 'ansteorra', 'status' => 'active']);
        $this->setSecret('tenant.ansteorra.db.password', 'legacy-database-password');

        $this->exec('platform secrets import-env');

        $this->assertExitSuccess();
        $this->assertErrorContains('Legacy environment secret import deferred');
        $this->assertSame(
            0,
            $this->platform()->execute('SELECT COUNT(*) FROM platform_secret_values')->fetchColumn(0),
        );
    }

    public function testMissingMasterKeyFailsWhenDatabaseDriverIsActive(): void
    {
        Configure::write('Secrets.driver', 'database');

        $this->exec('platform secrets import-env');

        $this->assertExitError();
        $this->assertErrorContains('database secret store master key is unavailable');
    }

    private function setSecret(string $name, string $value): void
    {
        $environmentName = self::ENV_PREFIX . strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '_', $name));
        $this->environmentNames[] = $environmentName;
        putenv(sprintf('%s=%s', $environmentName, $value));
    }

    private function createSchema(): void
    {
        $this->platform()->execute(
            'CREATE TABLE tenants (slug TEXT PRIMARY KEY, status TEXT NOT NULL, tenant_config TEXT NULL)',
        );
        $this->platform()->execute(
            'CREATE TABLE platform_users (id TEXT PRIMARY KEY, totp_secret_ref TEXT NULL)',
        );
        $this->platform()->execute(
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
        $this->platform()->execute(
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
        $this->platform()->execute(
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

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }
}
