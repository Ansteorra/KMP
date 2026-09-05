<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\PgRestoreTenantBackupRestorer;
use App\Services\Secrets\SensitiveString;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PgRestoreTenantBackupRestorerTest extends TestCase
{
    private string $directory;
    private mixed $originalAdmin;
    private mixed $originalPlatform;
    private array $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalAdmin = Configure::read('Database.adminJob');
        $this->originalPlatform = ConnectionManager::getConfig('platform');
        $this->directory = sys_get_temp_dir() . '/kmp-restore-process-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/backup.dump', 'synthetic archive; never read by PostgreSQL');
        file_put_contents($this->directory . '/pg_restore', '#!' . PHP_BINARY . "\n<?php\n" . <<<'FAKE'
$captured = ['argv' => $argv];
foreach (['PGPASSWORD', 'PGSSLMODE', 'PGSSLKEY', 'PGSSLCERT', 'PGSSLROOTCERT', 'PGHOSTADDR', 'PGSERVICE'] as $key) {
    $captured[$key] = getenv($key);
}
file_put_contents(__DIR__ . '/capture.json', json_encode($captured, JSON_THROW_ON_ERROR));
FAKE);
        chmod($this->directory . '/pg_restore', 0700);
        foreach (['PATH', 'PGHOSTADDR', 'PGSERVICE', 'PGSSLMODE'] as $variable) {
            $this->originalEnvironment[$variable] = getenv($variable);
        }
        putenv('PATH=' . $this->directory . ':' . (getenv('PATH') ?: ''));
        putenv('PGHOSTADDR=203.0.113.45');
        putenv('PGSERVICE=unrelated_service');
        putenv('PGSSLMODE=disable');
        Configure::write('Database.adminJob', true);
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->originalPlatform !== null) {
            ConnectionManager::setConfig('platform', $this->originalPlatform);
        }
        Configure::write('Database.adminJob', $this->originalAdmin);
        foreach ($this->originalEnvironment as $variable => $value) {
            putenv($value === false ? $variable : $variable . '=' . $value);
        }
        foreach (glob($this->directory . '/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function testRestoreUsesAdministrativePortCredentialsAndTlsPolicy(): void
    {
        $this->configureAdministrativeConnection([
            'port' => 6543,
            'ssl' => true,
            'ssl_mode' => 'verify-full',
            'ssl_ca' => '/synthetic/ca.pem',
            'ssl_cert' => '/synthetic/client.pem',
            'ssl_key' => '/synthetic/client.key',
        ]);
        $captured = $this->runRestore();
        $this->assertSame('6543', $captured['argv'][array_search('--port', $captured['argv'], true) + 1]);
        $this->assertSame('schema_owner', $captured['argv'][array_search('--username', $captured['argv'], true) + 1]);
        $this->assertSame('tenant_acme', $captured['argv'][array_search('--dbname', $captured['argv'], true) + 1]);
        $this->assertSame('synthetic-administrative-password', $captured['PGPASSWORD']);
        $this->assertSame('verify-full', $captured['PGSSLMODE']);
        $this->assertSame('/synthetic/ca.pem', $captured['PGSSLROOTCERT']);
        $this->assertSame('/synthetic/client.pem', $captured['PGSSLCERT']);
        $this->assertSame('/synthetic/client.key', $captured['PGSSLKEY']);
        $this->assertFalse($captured['PGHOSTADDR']);
        $this->assertFalse($captured['PGSERVICE']);
        $this->assertStringNotContainsString('password', implode(' ', $captured['argv']));
    }

    public function testLegacyDatabaseUrlSslmodeStillRequiresTls(): void
    {
        $this->configureAdministrativeConnection(['sslmode' => 'require']);
        $captured = $this->runRestore();
        $this->assertSame('require', $captured['PGSSLMODE']);
    }

    public function testDefaultConnectionIgnoresInheritedLibpqOverrides(): void
    {
        $this->configureAdministrativeConnection([]);
        $captured = $this->runRestore();
        $this->assertSame('5432', $captured['argv'][array_search('--port', $captured['argv'], true) + 1]);
        $this->assertSame('prefer', $captured['PGSSLMODE']);
        $this->assertFalse($captured['PGHOSTADDR']);
        $this->assertFalse($captured['PGSERVICE']);
        $this->assertFalse($captured['PGSSLROOTCERT']);
    }

    public function testInvalidAdministrativePortFailsBeforeLaunchingRestore(): void
    {
        $this->configureAdministrativeConnection(['port' => '0']);
        try {
            $this->runRestore();
            $this->fail('Invalid port must reject the restore.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unsafe administrative database port for pg_restore.', $exception->getMessage());
            $this->assertFileDoesNotExist($this->directory . '/capture.json');
        }
    }

    /**
     * @param array<string, mixed> $overrides Datasource overrides
     * @return void
     */
    private function configureAdministrativeConnection(array $overrides): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', array_merge([
            'className' => Connection::class,
            'driver' => Postgres::class,
            'host' => 'db.example.test',
            'database' => 'platform',
            'username' => 'schema_owner',
            'password' => 'synthetic-administrative-password',
        ], $overrides));
    }

    /**
     * Run only the temporary pg_restore recorder; no database is contacted.
     *
     * @return array<string, mixed> Captured subprocess arguments and synthetic credentials
     */
    private function runRestore(): array
    {
        $tenant = new TenantMetadata(
            'synthetic-id',
            'acme',
            'Acme',
            'suspended',
            'db.example.test',
            'tenant_acme',
            'tenant_runtime',
        );
        $result = (new PgRestoreTenantBackupRestorer())->restore(
            $tenant,
            new SensitiveString('synthetic-runtime-password'),
            $this->directory . '/backup.dump',
        );
        $this->assertSame([], $result);

        return json_decode((string)file_get_contents($this->directory . '/capture.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
