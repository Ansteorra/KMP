<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\LocalPlatformDatabaseBackupStorage;
use App\Services\Backups\PgDumpPlatformDatabaseBackupDumper;
use App\Services\Backups\PlatformDatabaseBackupDumperInterface;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Secrets\SensitiveString;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PlatformDatabaseBackupServiceTest extends TestCase
{
    private ?array $previousPlatformConfig = null;
    private string $backupRoot;

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
            'host' => 'db.example.test',
            'port' => 5432,
            'username' => 'platform_role',
            'password' => 'platform-db-password',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createPlatformSchema();
        $this->backupRoot = TMP . 'platform-backup-service-test-' . str_replace('.', '-', uniqid('', true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->backupRoot);
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testBackupCreatesCompletedMetadataAndEncryptedObject(): void
    {
        $plaintext = 'platform backup sample: do not store me';
        $secrets = new ArraySecretStore(['platform.backup.kek' => 'backup-kek']);
        $encryptor = new PlatformDatabaseBackupEncryptor();
        $service = $this->service($secrets, new FakePlatformDatabaseBackupDumper($plaintext), $encryptor);

        $result = $service->backupPlatformDatabase(14);

        $this->assertSame('completed', $result->status);
        $job = $this->platform()->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $this->assertSame('platform_database_backup', $job['job_type']);
        $backup = $this->platform()->execute('SELECT * FROM platform_database_backups')->fetch('assoc');
        $this->assertSame('completed', $backup['status']);
        $this->assertSame('pg_dump', $backup['backup_type']);
        $this->assertSame('platform', $backup['connection_name']);
        $this->assertSame(':memory:', $backup['database_name']);
        $this->assertSame($result->objectUri, $backup['object_uri']);
        $this->assertSame(PlatformDatabaseBackupEncryptor::DATA_ALGORITHM, $backup['encryption_algorithm']);
        $this->assertSame('platform.backup.kek', $backup['wrapped_dek_key_name']);
        $this->assertStringNotContainsString($plaintext, (string)$backup['wrapped_dek']);

        $storedPath = $this->storedPath($result->backupId);
        $cipherPayload = (string)file_get_contents($storedPath);
        $this->assertStringNotContainsString($plaintext, $cipherPayload);
        $decrypted = $encryptor->decryptFileForTest(
            $storedPath,
            (string)$backup['wrapped_dek'],
            json_decode((string)$backup['wrapped_dek_metadata'], true),
            new SensitiveString('backup-kek'),
        );
        $this->assertSame($plaintext, $decrypted);
    }

    public function testPgDumpArgvRejectsUnsafeValuesAndNeverIncludesPassword(): void
    {
        $dumper = new PgDumpPlatformDatabaseBackupDumper();
        $config = [
            'host' => 'db.example.test',
            'port' => 5432,
            'database' => 'platform_db',
            'username' => 'platform_role',
            'password' => 'super-secret-password',
        ];
        $argv = $dumper->buildArgv($config, $this->backupRoot . '/safe.dump');

        $this->assertContains('--dbname', $argv);
        $this->assertContains('platform_db', $argv);
        $this->assertNotContains('super-secret-password', $argv);
        $this->assertNotContains('PGPASSWORD=super-secret-password', $argv);

        try {
            $dumper->buildArgv(array_replace($config, ['database' => 'bad;name']), $this->backupRoot . '/unsafe.dump');
            $this->fail('Expected unsafe database name to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unsafe platform database name', $e->getMessage());
            $this->assertStringNotContainsString('super-secret-password', $e->getMessage());
        }
    }

    public function testDumperFailureRedactsPasswordInJobAndBackupErrors(): void
    {
        $service = $this->service(
            new ArraySecretStore(['platform.backup.kek' => 'backup-kek']),
            new FailingPlatformDatabaseBackupDumper(
                'pg_dump failed password=super-secret-password PGPASSWORD=super-secret-password',
            ),
            new PlatformDatabaseBackupEncryptor(),
        );

        try {
            $service->backupPlatformDatabase();
            $this->fail('Expected dumper failure.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('super-secret-password', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status, last_error FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()
            ->execute('SELECT status, error_summary FROM platform_database_backups')
            ->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
        $this->assertStringNotContainsString('super-secret-password', (string)$job['last_error']);
        $this->assertStringNotContainsString('super-secret-password', (string)$backup['error_summary']);
        $this->assertStringContainsString('[redacted]', (string)$job['last_error']);
    }

    public function testMissingKekSecretFailsCleanlyAndMarksBackupFailed(): void
    {
        $service = $this->service(
            new ArraySecretStore([]),
            new FakePlatformDatabaseBackupDumper('unused'),
            new PlatformDatabaseBackupEncryptor(),
        );

        try {
            $service->backupPlatformDatabase();
            $this->fail('Expected missing KEK failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Missing platform backup KEK secret.', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status, last_error FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()
            ->execute('SELECT status, error_summary FROM platform_database_backups')
            ->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
        $this->assertSame('Missing platform backup KEK secret.', $job['last_error']);
    }

    public function testDisabledLocalStorageFailsClosedAndMarksBackupFailed(): void
    {
        $service = new PlatformDatabaseBackupService(
            $this->platform(),
            (array)ConnectionManager::getConfig('platform'),
            new ArraySecretStore(['platform.backup.kek' => 'backup-kek']),
            new FakePlatformDatabaseBackupDumper('unused'),
            new PlatformDatabaseBackupEncryptor(),
            new LocalPlatformDatabaseBackupStorage($this->backupRoot, false, 'test'),
        );

        try {
            $service->backupPlatformDatabase();
            $this->fail('Expected disabled storage failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Local platform backup storage is disabled', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()->execute('SELECT status FROM platform_database_backups')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
    }

    public function testProductionLocalStorageFailsClosedEvenWhenEnabled(): void
    {
        $service = new PlatformDatabaseBackupService(
            $this->platform(),
            (array)ConnectionManager::getConfig('platform'),
            new ArraySecretStore(['platform.backup.kek' => 'backup-kek']),
            new FakePlatformDatabaseBackupDumper('unused'),
            new PlatformDatabaseBackupEncryptor(),
            new LocalPlatformDatabaseBackupStorage($this->backupRoot, true, 'production'),
        );

        try {
            $service->backupPlatformDatabase();
            $this->fail('Expected production local storage failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not allowed outside local/dev/test', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()->execute('SELECT status FROM platform_database_backups')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
    }

    private function service(
        ArraySecretStore $secrets,
        PlatformDatabaseBackupDumperInterface $dumper,
        PlatformDatabaseBackupEncryptor $encryptor,
    ): PlatformDatabaseBackupService {
        return new PlatformDatabaseBackupService(
            $this->platform(),
            (array)ConnectionManager::getConfig('platform'),
            $secrets,
            $dumper,
            $encryptor,
            new LocalPlatformDatabaseBackupStorage($this->backupRoot, true, 'test'),
        );
    }

    private function createPlatformSchema(): void
    {
        $this->platform()->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->platform()->execute(
            'CREATE TABLE platform_database_backups (
                id TEXT PRIMARY KEY,
                platform_job_id TEXT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                connection_name TEXT NOT NULL,
                database_name TEXT NOT NULL,
                object_uri TEXT NULL,
                object_size_bytes INTEGER NULL,
                object_sha256 TEXT NULL,
                encryption_algorithm TEXT NOT NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NOT NULL,
                wrapped_dek_key_version TEXT NOT NULL,
                wrapped_dek_metadata TEXT NULL,
                error_summary TEXT NULL,
                retention_until TEXT NULL,
                retention_policy TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                completed_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function storedPath(string $backupId): string
    {
        return $this->backupRoot . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'platform'
            . DIRECTORY_SEPARATOR . $backupId . '.pgdump.enc.json';
    }

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
