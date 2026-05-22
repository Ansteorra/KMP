<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use PDO;

class TenantPocCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private array $originalSecrets;
    private mixed $originalPlatformConfig = null;
    private mixed $originalDefaultConfig = null;
    /**
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    /**
     * @var list<string>
     */
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSecrets = (array)Configure::read('Secrets');
        $this->originalPlatformConfig = ConnectionManager::getConfig('platform');
        $this->originalDefaultConfig = ConnectionManager::getConfig('default');
        $this->captureEnv('KMP_ENABLE_TENANT_POC');
        $this->captureEnv('KMP_ALLOW_PRODUCTION_TENANT_POC');
        $this->captureEnv('APP_ENV');
        $this->configureSecrets();
        $this->configureDefaultSqlite();
        $this->configurePlatform();
    }

    protected function tearDown(): void
    {
        foreach (['platform', 'default', 'tenant'] as $connection) {
            if (in_array($connection, ConnectionManager::configured(), true)) {
                ConnectionManager::drop($connection);
            }
        }
        if ($this->originalPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->originalPlatformConfig);
        }
        if ($this->originalDefaultConfig !== null) {
            ConnectionManager::setConfig('default', $this->originalDefaultConfig);
        }
        Configure::write('Secrets', $this->originalSecrets);
        foreach ($this->originalEnv as $name => $value) {
            $value === false ? putenv($name) : putenv($name . '=' . $value);
        }
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testRequiresExplicitEnvironmentFlagAndConfirmation(): void
    {
        $this->exec('tenant_poc --verify-only');

        $this->assertExitError();
        $this->assertErrorContains('Pass --yes');

        $this->exec('tenant_poc --verify-only --yes');

        $this->assertExitError();
        $this->assertErrorContains('KMP_ENABLE_TENANT_POC=true');
    }

    public function testVerifyOnlyResolvesBothHostsAndSmokesTenantDatabases(): void
    {
        putenv('KMP_ENABLE_TENANT_POC=true');

        $this->exec(
            'tenant_poc --verify-only --yes '
            . '--tenant-a poc-alpha --tenant-b poc-beta '
            . '--host-a alpha.example.test --host-b beta.example.test',
        );

        $this->assertExitSuccess();
        $this->assertOutputContains('Tenant poc-alpha smoke passed');
        $this->assertOutputContains('Tenant poc-beta smoke passed');
        $this->assertOutputContains('Two-tenant POC verification passed.');
    }

    public function testProductionRequiresAdditionalOptIn(): void
    {
        putenv('KMP_ENABLE_TENANT_POC=true');
        putenv('APP_ENV=production');

        $this->exec('tenant_poc --verify-only --yes');

        $this->assertExitError();
        $this->assertErrorContains('Refusing to run in production');
    }

    private function configureSecrets(): void
    {
        $secretFile = ROOT . DS . 'tmp' . DS . 'tests' . DS . 'tenant-poc-secrets.json';
        $this->paths[] = $secretFile;
        if (file_exists($secretFile)) {
            unlink($secretFile);
        }
        if (!is_dir(dirname($secretFile))) {
            mkdir(dirname($secretFile), 0700, true);
        }
        file_put_contents($secretFile, json_encode([
            'secrets' => [
                'tenant.poc-alpha.db.password' => ['value' => 'unused'],
                'tenant.poc-beta.db.password' => ['value' => 'unused'],
            ],
        ], JSON_PRETTY_PRINT));
        chmod($secretFile, 0600);

        Configure::write('Secrets', [
            'driver' => 'file',
            'drivers' => [
                'file' => [
                    'path' => $secretFile,
                    'environment' => 'test',
                    'allowInEnvironments' => ['test'],
                ],
            ],
        ]);
    }

    private function configureDefaultSqlite(): void
    {
        if (in_array('default', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('default');
        }
        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'cacheMetadata' => false,
        ]);
    }

    private function configurePlatform(): void
    {
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'cacheMetadata' => false,
        ]);
        $connection = ConnectionManager::get('platform');
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT,
                display_name TEXT,
                status TEXT,
                db_server TEXT,
                db_name TEXT,
                db_role TEXT,
                schema_version TEXT,
                tenant_config TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_hosts (
                id TEXT PRIMARY KEY,
                tenant_id TEXT,
                host_normalized TEXT,
                status TEXT
            )',
        );

        $this->insertTenant('poc-alpha', 'alpha.example.test', 2);
        $this->insertTenant('poc-beta', 'beta.example.test', 3);
    }

    private function insertTenant(string $slug, string $host, int $memberRows): void
    {
        $tenantDb = ROOT . DS . 'tmp' . DS . 'tests' . DS . $slug . '.sqlite';
        $this->paths[] = $tenantDb;
        if (file_exists($tenantDb)) {
            unlink($tenantDb);
        }
        $pdo = new PDO('sqlite:' . $tenantDb);
        $pdo->exec('CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, sca_name TEXT)');
        for ($i = 1; $i <= $memberRows; $i++) {
            $pdo->exec("INSERT INTO members (sca_name) VALUES ('Member {$i}')");
        }

        $connection = ConnectionManager::get('platform');
        $tenantId = 'tenant-' . $slug;
        $connection->insert('tenants', [
            'id' => $tenantId,
            'slug' => $slug,
            'display_name' => $slug,
            'status' => 'active',
            'db_server' => 'localhost',
            'db_name' => $tenantDb,
            'db_role' => 'ignored',
            'schema_version' => '20260516000000',
            'tenant_config' => '{}',
        ]);
        $connection->insert('tenant_hosts', [
            'id' => 'host-' . $slug,
            'tenant_id' => $tenantId,
            'host_normalized' => $host,
            'status' => 'active',
        ]);
    }

    private function captureEnv(string $name): void
    {
        $this->originalEnv[$name] = getenv($name);
        putenv($name);
    }
}
