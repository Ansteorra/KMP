<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformQueueService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RuntimeException;

class PlatformQueueServiceTest extends TestCase
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
        $this->createPlatformSchema();
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testEnqueueUsesIdempotencyKey(): void
    {
        $tenantId = $this->insertTenant('alpha');
        $service = new PlatformQueueService();

        $first = $service->enqueue($tenantId, 'App\\Job\\SyncMember', ['member' => 'first'], [
            'idempotencyKey' => 'alpha-sync-1',
            'now' => '2026-05-16 00:00:00',
        ]);
        $second = $service->enqueue($tenantId, 'App\\Job\\SyncMember', ['member' => 'second'], [
            'idempotencyKey' => 'alpha-sync-1',
            'now' => '2026-05-16 00:01:00',
        ]);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(['member' => 'first'], $second['payload']);
        $count = $this->platform()->execute('SELECT COUNT(*) FROM queue_messages')->fetchColumn(0);
        $this->assertSame(1, (int)$count);
    }

    public function testPreviewClaimableSkipsLockedFutureInactiveTenantAndSchema(): void
    {
        $activeTenant = $this->insertTenant('alpha');
        $inactiveTenant = $this->insertTenant('inactive', 'suspended');
        $service = new PlatformQueueService();

        $eligible = $service->enqueue($activeTenant, 'EligibleJob', [], [
            'priority' => 10,
            'now' => '2026-05-16 00:00:00',
        ]);
        $locked = $service->enqueue($activeTenant, 'LockedJob', [], ['now' => '2026-05-16 00:00:01']);
        $future = $service->enqueue($activeTenant, 'FutureJob', [], [
            'notBefore' => '2026-05-16 01:00:00',
            'now' => '2026-05-16 00:00:02',
        ]);
        $inactive = $service->enqueue($inactiveTenant, 'InactiveJob', [], ['now' => '2026-05-16 00:00:03']);
        $schemaBlocked = $service->enqueue($activeTenant, 'SchemaJob', [], [
            'minConsumerSchema' => '20260516009999',
            'now' => '2026-05-16 00:00:04',
        ]);
        $this->platform()->update('queue_messages', [
            'status' => PlatformQueueService::STATUS_RUNNING,
            'locked_until' => '2026-05-16 00:10:00',
        ], ['id' => $locked['id']]);

        $messages = $service->previewClaimable(10, '20260516005000', '2026-05-16 00:05:00');

        $this->assertSame([$eligible['id']], array_column($messages, 'id'));
        $this->assertNotContains($future['id'], array_column($messages, 'id'));
        $this->assertNotContains($inactive['id'], array_column($messages, 'id'));
        $this->assertNotContains($schemaBlocked['id'], array_column($messages, 'id'));

        $sql = PlatformQueueService::claimSql(5);
        $this->assertStringContainsString('FOR UPDATE OF t SKIP LOCKED', $sql);
        $this->assertStringContainsString('FOR UPDATE OF qm SKIP LOCKED', $sql);
        $this->assertStringContainsString("t.status = 'active'", $sql);
        $this->assertStringContainsString('qm.min_consumer_schema <= CAST(:consumerSchema AS varchar)', $sql);
    }

    public function testPreviewClaimableRespectsTenantConcurrencyLimit(): void
    {
        $cappedTenant = $this->insertTenant('capped', 'active', 1);
        $openTenant = $this->insertTenant('open', 'active', 2);
        $service = new PlatformQueueService();

        $running = $service->enqueue($cappedTenant, 'RunningJob', [], ['now' => '2026-05-16 00:00:00']);
        $service->enqueue($cappedTenant, 'BlockedByCapJob', [], ['now' => '2026-05-16 00:00:01']);
        $openOne = $service->enqueue($openTenant, 'OpenJobOne', [], ['now' => '2026-05-16 00:00:02']);
        $openTwo = $service->enqueue($openTenant, 'OpenJobTwo', [], ['now' => '2026-05-16 00:00:03']);
        $this->platform()->update('queue_messages', [
            'status' => PlatformQueueService::STATUS_RUNNING,
            'locked_until' => '2026-05-16 00:10:00',
        ], ['id' => $running['id']]);

        $messages = $service->previewClaimable(10, null, '2026-05-16 00:05:00');

        $this->assertSame([$openOne['id'], $openTwo['id']], array_column($messages, 'id'));
    }

    public function testClaimFailsClearlyWhenConnectionIsNotPostgres(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires PostgreSQL FOR UPDATE SKIP LOCKED support');

        (new PlatformQueueService())->claim(1, 'worker-1', null, 300, '2026-05-16 00:00:00');
    }

    public function testFinalFailureCopiesDeadLetterAndScrubsSensitiveError(): void
    {
        $tenantId = $this->insertTenant('alpha');
        $service = new PlatformQueueService();
        $message = $service->enqueue($tenantId, 'SensitiveJob', ['ref' => 'opaque-id'], [
            'maxAttempts' => 1,
            'idempotencyKey' => 'sensitive-1',
            'now' => '2026-05-16 00:00:00',
        ]);
        $this->platform()->update('queue_messages', [
            'status' => PlatformQueueService::STATUS_RUNNING,
            'attempts' => 1,
            'locked_by' => 'worker-1',
            'locked_until' => '2026-05-16 00:10:00',
        ], ['id' => $message['id']]);

        $service->fail($message['id'], 'Failed for admin.person@example.test with Bearer abc.def', 0, '2026-05-16 00:05:00');

        $stored = $this->platform()
            ->execute('SELECT * FROM queue_messages WHERE id = :id', ['id' => $message['id']])
            ->fetch('assoc');
        $dead = $this->platform()->execute('SELECT * FROM queue_dead_letter')->fetch('assoc');

        $this->assertSame(PlatformQueueService::STATUS_DEAD_LETTER, $stored['status']);
        $this->assertSame('Failed for [redacted-email] with Bearer [redacted-token]', $stored['last_error']);
        $this->assertSame($message['id'], $dead['original_message_id']);
        $this->assertSame($tenantId, $dead['tenant_id']);
        $this->assertSame('Failed for [redacted-email] with Bearer [redacted-token]', $dead['failed_reason']);
        $this->assertStringNotContainsString('admin.person@example.test', $dead['failed_reason']);
        $this->assertSame(['ref' => 'opaque-id'], json_decode((string)$dead['payload'], true));
    }

    private function createPlatformSchema(): void
    {
        $connection = $this->platform();
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                queue_concurrency_limit INTEGER NOT NULL DEFAULT 5
            )',
        );
        $connection->execute(
            'CREATE TABLE queue_messages (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "queued",
                priority INTEGER NOT NULL DEFAULT 100,
                not_before TEXT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                locked_by TEXT NULL,
                locked_until TEXT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                failed_at TEXT NULL,
                last_error TEXT NULL,
                producer_schema TEXT NULL,
                min_consumer_schema TEXT NULL,
                idempotency_key TEXT UNIQUE NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE queue_dead_letter (
                id TEXT PRIMARY KEY,
                original_message_id TEXT NOT NULL,
                tenant_id TEXT NOT NULL,
                job_class TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "dead_letter",
                priority INTEGER NOT NULL DEFAULT 100,
                not_before TEXT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                locked_by TEXT NULL,
                locked_until TEXT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                failed_at TEXT NULL,
                last_error TEXT NULL,
                producer_schema TEXT NULL,
                min_consumer_schema TEXT NULL,
                idempotency_key TEXT NULL,
                failed_reason TEXT NOT NULL,
                dead_lettered_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function insertTenant(string $slug, string $status = 'active', int $queueConcurrencyLimit = 5): string
    {
        $id = Text::uuid();
        $this->platform()->insert('tenants', [
            'id' => $id,
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => $status,
            'queue_concurrency_limit' => $queueConcurrencyLimit,
        ]);

        return $id;
    }

    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }
}
