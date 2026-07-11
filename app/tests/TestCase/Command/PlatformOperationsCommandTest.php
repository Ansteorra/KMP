<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class PlatformOperationsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private string $secretFile;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $previousPlatformConfig;

    /**
     * @var array<string, mixed>
     */
    private array $previousSecretsConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        $this->previousSecretsConfig = (array)Configure::read('Secrets', []);
        $this->secretFile = TMP . 'platform-operations-command-secrets-' . uniqid('', true) . '.json';
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
        if (is_file($this->secretFile)) {
            unlink($this->secretFile);
        }
        Configure::write('Secrets', $this->previousSecretsConfig);
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testBackupKeyReconciliationIsIdempotentAndDoesNotPrintValues(): void
    {
        $this->platform()->insert('tenants', ['slug' => 'alpha', 'status' => 'active']);
        $this->platform()->insert('tenants', ['slug' => 'beta', 'status' => 'suspended']);
        $this->platform()->insert('tenants', ['slug' => 'retired', 'status' => 'archived']);

        $this->exec('platform backup-keys ensure');

        $this->assertExitSuccess();
        $this->assertOutputContains('3 created, 0 already present');
        $store = SecretStoreFactory::fromConfig();
        foreach (['platform.backup.kek', 'tenant.alpha.kek', 'tenant.beta.kek'] as $name) {
            $secret = $store->get($name);
            $this->assertNotNull($secret);
            $this->assertNotSame('', $secret->reveal());
            $this->assertOutputNotContains($secret->reveal());
        }
        $this->assertNull($store->get('tenant.retired.kek'));

        $this->exec('platform backup-keys ensure');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 created, 3 already present');
    }

    public function testMetricsPruneCommandDeletesOnlyExpiredAggregates(): void
    {
        $this->platform()->insert('tenant_request_metrics_hourly', [
            'id' => 'old',
            'metric_hour' => '2020-01-01 00:00:00',
        ]);
        $this->platform()->insert('tenant_request_metrics_hourly', [
            'id' => 'current',
            'metric_hour' => gmdate('Y-m-d H:00:00'),
        ]);

        $this->exec('platform metrics prune --retention-days 90');

        $this->assertExitSuccess();
        $this->assertOutputContains('Deleted 1 expired tenant metric aggregates.');
        $rows = $this->platform()->execute(
            'SELECT id FROM tenant_request_metrics_hourly ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame([['id' => 'current']], $rows);
    }

    public function testBackupPruneCommandRejectsUnsafeLimit(): void
    {
        $this->exec('platform backups prune --limit 1001');

        $this->assertExitError();
        $this->assertErrorContains('between 1 and 1000');
    }

    public function testPlatformBackupDecryptCommandWritesOnlyTheRequestedPlaintextFile(): void
    {
        $backupId = '11111111-1111-4111-8111-111111111111';
        $kek = 'platform-command-test-kek';
        $inputPath = tempnam(TMP, 'platform-command-plaintext-');
        $encryptedPath = tempnam(TMP, 'platform-command-encrypted-');
        $keyPath = tempnam(TMP, 'platform-command-key-');
        $outputPath = TMP . 'platform-command-output-' . uniqid('', true) . '.pgdump';
        $this->assertIsString($inputPath);
        $this->assertIsString($encryptedPath);
        $this->assertIsString($keyPath);
        try {
            file_put_contents($inputPath, 'postgres custom-format dump');
            $encryption = (new PlatformDatabaseBackupEncryptor())->encryptFile(
                $inputPath,
                $encryptedPath,
                $backupId,
                new SensitiveString($kek),
                PlatformDatabaseBackupService::KEK_SECRET_NAME,
                'v1',
            );
            $backup = [
                'id' => $backupId,
                'backup_type' => PlatformDatabaseBackupService::BACKUP_TYPE,
                'status' => 'completed',
                'object_size_bytes' => filesize($encryptedPath),
                'object_sha256' => hash_file('sha256', $encryptedPath),
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_key_name' => PlatformDatabaseBackupService::KEK_SECRET_NAME,
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_THROW_ON_ERROR),
            ];
            $export = (new BackupRecoveryKeyService())->exportPlatform(
                $backup,
                new ArraySecretStore([PlatformDatabaseBackupService::KEK_SECRET_NAME => $kek]),
            );
            file_put_contents($keyPath, $export['content']);

            $this->exec(sprintf(
                'platform backup decrypt --archive %s --recovery-key %s --output %s ' .
                '--confirm WRITE-PLAINTEXT-PLATFORM-BACKUP',
                $encryptedPath,
                $keyPath,
                $outputPath,
            ));

            $this->assertExitSuccess();
            $this->assertSame('postgres custom-format dump', file_get_contents($outputPath));
            $this->assertOutputNotContains($kek);
        } finally {
            foreach ([$inputPath, $encryptedPath, $keyPath, $outputPath] as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function createSchema(): void
    {
        $this->platform()->execute(
            'CREATE TABLE tenants (
                slug TEXT PRIMARY KEY,
                status TEXT NOT NULL
            )',
        );
        $this->platform()->execute(
            'CREATE TABLE tenant_request_metrics_hourly (
                id TEXT PRIMARY KEY,
                metric_hour TEXT NOT NULL
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
