<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformJobRunner;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;

class PlatformJobRunnerTest extends TestCase
{
    private Connection $connection;

    /**
     * @var array<string, mixed>
     */
    private array $previousSecrets = [];

    private string $secretFile = '';

    private string $secretDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousSecrets = (array)Configure::read('Secrets');
        $this->secretDirectory = TMP . 'tests' . DS . 'platform-job-runner-secrets-' . uniqid('', true);
        mkdir($this->secretDirectory, 0700, true);
        $this->secretFile = $this->secretDirectory . DS . 'secrets.json';
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

        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Configure::write('Secrets', $this->previousSecrets);
        if ($this->secretFile !== '' && file_exists($this->secretFile)) {
            unlink($this->secretFile);
        }
        if ($this->secretDirectory !== '' && is_dir($this->secretDirectory)) {
            rmdir($this->secretDirectory);
        }
        parent::tearDown();
    }

    public function testRunsTenantProvisioningJobWithoutLeakingSecrets(): void
    {
        $this->connection->insert('platform_jobs', [
            'id' => 'job-1',
            'tenant_id' => null,
            'requested_by_platform_user_id' => 'platform-admin-1',
            'job_type' => PlatformJobRunner::JOB_TENANT_PROVISION,
            'status' => 'queued',
            'idempotency_key' => 'tenant_provision:acme:test',
            'parameters' => json_encode([
                'slug' => 'acme',
                'display_name' => 'Acme Kingdom',
                'primary_host' => 'acme.example.test',
                'db_server' => 'db.internal',
                'db_name' => 'kmp_tenant_acme',
                'db_role' => 'kmp_tenant_acme_role',
                'blob_container' => 'tenant-acme',
                'region' => 'us',
                'queue_concurrency_limit' => 4,
                'tenantConfig' => [
                    'documents' => ['blob_container' => 'tenant-acme'],
                    'email' => ['mode' => 'disabled'],
                ],
                'status' => 'provisioning',
                'skip_create_database' => true,
                'run_migrations' => false,
            ], JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => '2026-05-16 12:00:00',
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => '2026-05-16 12:00:00',
        ]);

        $result = (new PlatformJobRunner($this->connection))->run(10, static fn(): int => 0);

        $this->assertSame(['claimed' => 1, 'completed' => 1, 'failed' => 0], $result);
        $tenant = $this->connection->execute('SELECT * FROM tenants WHERE slug = ?', ['acme'])->fetch('assoc');
        $this->assertSame('Acme Kingdom', $tenant['display_name']);
        $this->assertSame('provisioning', $tenant['status']);
        $this->assertSame(4, (int)$tenant['queue_concurrency_limit']);

        $job = $this->connection->execute('SELECT * FROM platform_jobs WHERE id = ?', ['job-1'])->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $this->assertSame($tenant['id'], $job['tenant_id']);
        $this->assertStringNotContainsString('password', (string)$job['parameters']);
        $this->assertStringNotContainsString('secret', (string)$job['parameters']);
    }

    private function createSchema(): void
    {
        $this->connection->execute(
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
                key_vault_prefix TEXT,
                schema_version TEXT,
                feature_flags TEXT,
                tenant_config TEXT,
                queue_concurrency_limit INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                activated_at TEXT,
                suspended_at TEXT,
                archived_at TEXT,
                modified_at TEXT
            )',
        );
        $this->connection->execute(
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
        $this->connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL UNIQUE,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }
}
