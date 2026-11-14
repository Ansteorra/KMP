<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Cache\TenantAwareCache;
use App\Services\Platform\Audit\NullWormAuditSink;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\TenantHostResolver;
use App\Services\Platform\TenantLifecycleService;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

/** Real PostgreSQL snapshots and independent replica caches; no shared platform tables are changed. */
class TenantHostCachePostgresTest extends BaseTestCase
{
    private mixed $previousPlatformConfig;
    private Connection $schemaAdmin;
    private Connection $platformConnection;
    private Connection $reader;
    private string $schemaName;

    protected function setUp(): void
    {
        parent::setUp();
        $config = ConnectionManager::getConfig('test');
        $this->schemaAdmin = new Connection($config);
        $this->assertInstanceOf(Postgres::class, $this->schemaAdmin->getDriver());
        $this->schemaName = 'security_host_cache_' . bin2hex(random_bytes(6));
        $this->schemaAdmin->execute('CREATE SCHEMA ' . $this->schemaName);
        $config['schema'] = $this->schemaName;
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        TenantHostResolver::clearCache();
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', $config);
        $this->platformConnection = ConnectionManager::get('platform');
        $this->reader = new Connection($config);
        $this->platformConnection->execute('CREATE TABLE tenants (
            id text PRIMARY KEY, slug text, display_name text, status text, db_server text, db_name text,
            db_role text, schema_version text, tenant_config text, activated_at text, suspended_at text,
            archived_at text, modified_at text
        )');
        $this->platformConnection->execute('CREATE TABLE tenant_hosts (
            id text PRIMARY KEY, tenant_id text, host_normalized text, status text
        )');
        $this->platformConnection->execute('CREATE TABLE platform_jobs (
            id text PRIMARY KEY, tenant_id text, job_type text, status text
        )');
        $this->platformConnection->execute('CREATE TABLE audit_events (
            id bigserial PRIMARY KEY, tenant_id text, platform_user_id text, action text, subject_type text,
            subject_id text, reason text, metadata text, ip_address text, user_agent text,
            previous_hash text, event_hash text, created_at text
        )');
        foreach (['alpha', 'beta'] as $slug) {
            $this->platformConnection->insert('tenants', [
                'id' => $slug, 'slug' => $slug, 'display_name' => $slug, 'status' => 'active',
                'db_server' => 'db', 'db_name' => $slug . '_synthetic', 'db_role' => $slug . '_role',
                'schema_version' => 'synthetic-current', 'tenant_config' => '{}',
            ]);
            $this->platformConnection->insert('tenant_hosts', [
                'id' => $slug, 'tenant_id' => $slug, 'host_normalized' => $slug . '.example.test', 'status' => 'active',
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->platformConnection->inTransaction()) {
                $this->platformConnection->rollback();
            }
            TenantHostResolver::clearCache();
            $this->reader->getDriver()->disconnect();
            ConnectionManager::drop('platform');
            if ($this->previousPlatformConfig !== null) {
                ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
            }
            $this->schemaAdmin->execute('DROP SCHEMA ' . $this->schemaName . ' CASCADE');
        } finally {
            parent::tearDown();
        }
    }

    public function testSuspensionInvalidatesOnlyAfterTheRealOuterCommit(): void
    {
        $resolver = new TenantHostResolver();
        $this->assertSame('active', $resolver->resolve('alpha.example.test')?->status);
        $this->platformConnection->begin();
        $this->lifecycle()->transition('alpha', 'suspended', null, 'Synthetic tenant suspension regression.');
        $this->assertSame('active', $this->reader->execute("SELECT status FROM tenants WHERE id = 'alpha'")->fetchColumn(0));
        $this->assertSame('active', $resolver->resolve('alpha.example.test')?->status);
        $this->platformConnection->commit();

        $this->assertSame('suspended', $resolver->resolve('alpha.example.test')?->status);
        $this->assertSame('active', $resolver->resolve('beta.example.test')?->status);
    }

    public function testRollbackCannotPublishSuspensionToOtherConnections(): void
    {
        $this->platformConnection->begin();
        $this->lifecycle()->transition('alpha', 'suspended', null, 'Synthetic rollback regression.');
        $this->assertSame('suspended', (new TenantHostResolver())->resolve('alpha.example.test')?->status);
        $this->assertSame('active', $this->reader->execute("SELECT status FROM tenants WHERE id = 'alpha'")->fetchColumn(0));
        $this->platformConnection->rollback();

        $this->assertSame('active', (new TenantHostResolver())->resolve('alpha.example.test')?->status);
        $this->assertSame(0, (int)$this->reader->execute('SELECT count(*) FROM audit_events')->fetchColumn(0));
    }

    public function testAReplicaCannotRetainAnExpiredHostMappingToTheWrongTenant(): void
    {
        $cache = new class extends TenantAwareCache {
            public array $entries = [];

            public function readPlatform(string $key, string $config = 'default'): mixed
            {
                return $this->entries[$key] ?? null;
            }

            public function writePlatform(string $key, mixed $value, string $config = 'default'): bool
            {
                $this->entries[$key] = $value;

                return true;
            }
        };
        $replica = new TenantHostResolver('platform', $cache);
        $this->assertSame('alpha', $replica->resolve('alpha.example.test')?->slug);
        $this->reader->update('tenant_hosts', ['tenant_id' => 'beta'], ['id' => 'alpha']);
        foreach ($cache->entries as &$entry) {
            $entry['cached_at'] = time() - 31;
        }
        unset($entry);

        $this->assertSame('beta', $replica->resolve('alpha.example.test')?->slug);
        $this->assertSame('beta', $replica->resolve('beta.example.test')?->slug);
        $this->assertSame('alpha', $this->reader->execute("SELECT slug FROM tenants WHERE id = 'alpha'")->fetchColumn(0));
    }

    private function lifecycle(): TenantLifecycleService
    {
        return new TenantLifecycleService(
            $this->platformConnection,
            new PlatformAuditService($this->platformConnection, new NullWormAuditSink(), false),
            'synthetic-current',
        );
    }
}
