<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Tests tenant provisioning command validation and metadata-only idempotency.
 */
class TenantProvisionCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private array $originalSecrets;
    private ?array $originalPlatformConfig;
    private string $secretFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSecrets = (array)Configure::read('Secrets');
        $this->originalPlatformConfig = ConnectionManager::getConfig('platform');
        $this->secretFile = ROOT . DS . 'tmp' . DS . 'tests' . DS . 'tenant-provision-secrets.json';
        if (file_exists($this->secretFile)) {
            unlink($this->secretFile);
        }

        Configure::write('Secrets', [
            'driver' => 'file',
            'drivers' => [
                'file' => [
                    'path' => $this->secretFile,
                    'environment' => 'test',
                    'allowInEnvironments' => ['test'],
                ],
            ],
        ]);

        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'host' => 'localhost',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createPlatformTables();
    }

    protected function tearDown(): void
    {
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        if ($this->originalPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->originalPlatformConfig);
        }
        Configure::write('Secrets', $this->originalSecrets);
        if (file_exists($this->secretFile)) {
            unlink($this->secretFile);
        }

        parent::tearDown();
    }

    public function testMetadataOnlyProvisionIsIdempotentAndRedactsPassword(): void
    {
        $command = 'tenant provision acme --display-name "Acme Test" --host acme.example.test '
            . '--skip-create-database --skip-migrations --status provisioning';

        $this->exec($command);
        $this->assertExitSuccess();
        $this->assertOutputContains('Tenant metadata ready: acme');
        $this->assertOutputNotContains('Generated database password:');

        $connection = ConnectionManager::get('platform');
        $this->assertSame(1, (int)$connection->execute('SELECT COUNT(*) FROM tenants')->fetchColumn(0));
        $this->assertSame(1, (int)$connection->execute('SELECT COUNT(*) FROM tenant_hosts')->fetchColumn(0));
        $firstSecret = $this->readStoredSecret('tenant.acme.db.password');
        $this->assertNotSame('', $firstSecret);

        $this->exec($command);
        $this->assertExitSuccess();
        $this->assertSame(1, (int)$connection->execute('SELECT COUNT(*) FROM tenants')->fetchColumn(0));
        $this->assertSame(1, (int)$connection->execute('SELECT COUNT(*) FROM tenant_hosts')->fetchColumn(0));
        $this->assertSame($firstSecret, $this->readStoredSecret('tenant.acme.db.password'));
    }

    public function testShowPasswordExplicitlyPrintsPassword(): void
    {
        $this->exec(
            'tenant provision beta --display-name Beta --host beta.example.test '
            . '--skip-create-database --skip-migrations --status provisioning --show-password',
        );

        $this->assertExitSuccess();
        $this->assertOutputContains('Generated database password:');
        $this->assertOutputContains($this->readStoredSecret('tenant.beta.db.password'));
    }

    public function testRejectsUnsafeSlugBeforeWritingSecret(): void
    {
        $this->exec(
            'tenant provision "bad;slug" --display-name Bad --host bad.example.test '
            . '--skip-create-database --skip-migrations --status provisioning',
        );

        $this->assertExitError();
        $this->assertErrorContains('Invalid slug');
        $this->assertFileDoesNotExist($this->secretFile);
    }

    public function testCannotMarkActiveWhenMigrationsAreSkipped(): void
    {
        $this->exec(
            'tenant provision gamma --display-name Gamma --host gamma.example.test '
            . '--skip-create-database --skip-migrations',
        );

        $this->assertExitError();
        $this->assertErrorContains('Cannot set status=active when --skip-migrations is used.');
    }

    public function testDatabaseCreationMustBeExplicitBeforeMetadataIsWritten(): void
    {
        $this->exec('tenant provision delta --display-name Delta --host delta.example.test');

        $this->assertExitError();
        $this->assertErrorContains('Database creation is disabled.');
        $this->assertFileDoesNotExist($this->secretFile);
        $connection = ConnectionManager::get('platform');
        $this->assertSame(0, (int)$connection->execute('SELECT COUNT(*) FROM tenants')->fetchColumn(0));
    }

    private function createPlatformTables(): void
    {
        $connection = ConnectionManager::get('platform');
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                region TEXT NOT NULL,
                primary_host TEXT,
                db_server TEXT NOT NULL,
                db_name TEXT NOT NULL UNIQUE,
                db_role TEXT NOT NULL,
                schema_version TEXT,
                tenant_config TEXT,
                created_at TEXT NOT NULL,
                activated_at TEXT,
                suspended_at TEXT,
                archived_at TEXT,
                modified_at TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_hosts (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                host TEXT NOT NULL,
                host_normalized TEXT NOT NULL UNIQUE,
                is_primary INTEGER NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT
            )',
        );
    }

    private function readStoredSecret(string $name): string
    {
        $payload = json_decode((string)file_get_contents($this->secretFile), true);

        return (string)$payload['secrets'][$name]['value'];
    }
}
