<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\Audit\FileWormAuditSink;
use App\Services\Platform\Audit\NullWormAuditSink;
use App\Services\Platform\Audit\WormAuditSinkFactory;
use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;

class PlatformAuditServiceTest extends TestCase
{
    private Connection $connection;
    private string $wormFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->connection->execute(
            'CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT NULL,
                platform_user_id TEXT NULL,
                action TEXT NOT NULL,
                subject_type TEXT NULL,
                subject_id TEXT NULL,
                reason TEXT NULL,
                metadata TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                previous_hash TEXT NULL,
                event_hash TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );

        $directory = ROOT . DS . 'tmp' . DS . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $this->wormFile = $directory . DS . 'platform-audit-worm-' . str_replace('.', '-', uniqid('', true)) . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->wormFile)) {
            unlink($this->wormFile);
        }
        parent::tearDown();
    }

    public function testFileSinkAppendsHashChainedMirrorRecords(): void
    {
        $service = new PlatformAuditService($this->connection, new FileWormAuditSink($this->wormFile), true);

        $first = $service->record(
            'platform.admin.bootstrap.created',
            'user-1',
            'platform_user',
            'user-1',
            null,
            ['page_on_call' => true],
            true,
            ['createdAt' => '2026-05-16 01:00:00'],
        );
        $second = $service->record(
            'platform.admin.reset_mfa.refused',
            'user-1',
            'platform_user',
            'user-1',
            'approval unavailable',
            ['page_on_call' => true],
            true,
            ['createdAt' => '2026-05-16 01:01:00'],
        );

        $rows = $this->connection->execute('SELECT * FROM audit_events ORDER BY id ASC')->fetchAll('assoc');
        $this->assertCount(2, $rows);
        $this->assertSame($first['event_hash'], $rows[0]['event_hash']);
        $this->assertSame($second['event_hash'], $rows[1]['event_hash']);
        $this->assertSame($first['event_hash'], $second['previous_hash']);

        $records = $this->readWormRecords();
        $this->assertCount(2, $records);
        $this->assertSame('kmp.platform_audit_worm.v1', $records[0]['schema']);
        $this->assertNull($records[0]['mirror_previous_hash']);
        $this->assertSame($records[0]['mirror_hash'], $records[1]['mirror_previous_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $records[0]['event_digest']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $records[0]['mirror_hash']);
        $this->assertSame($first['event_hash'], $records[0]['event']['event_hash']);
        $this->assertSame($second['event_hash'], $records[1]['event']['event_hash']);
    }

    public function testWormMirrorRedactsPlaintextSecrets(): void
    {
        $service = new PlatformAuditService($this->connection, new FileWormAuditSink($this->wormFile), true);

        $service->record(
            'platform.admin.secret.test',
            'user-1',
            'platform_user',
            'user-1',
            null,
            [
                'password' => 'plain-password',
                'recovery_code' => 'RECOVERY-CODE',
                'nested' => [
                    'session_token' => 'token-value',
                    'connection_string' => 'AccountKey=secret',
                    'safe_reference' => 'opaque-id',
                ],
            ],
            true,
            ['createdAt' => '2026-05-16 01:00:00'],
        );

        $mirrorJson = (string)file_get_contents($this->wormFile);
        $this->assertStringNotContainsString('plain-password', $mirrorJson);
        $this->assertStringNotContainsString('RECOVERY-CODE', $mirrorJson);
        $this->assertStringNotContainsString('token-value', $mirrorJson);
        $this->assertStringNotContainsString('AccountKey=secret', $mirrorJson);
        $this->assertStringContainsString('opaque-id', $mirrorJson);
        $this->assertStringContainsString('[redacted]', $mirrorJson);

        $databaseJson = json_encode($this->connection->execute('SELECT * FROM audit_events')->fetchAll('assoc'));
        $this->assertIsString($databaseJson);
        $this->assertStringContainsString('plain-password', $databaseJson);
    }

    public function testDisabledFactoryIsExplicitSafeDefault(): void
    {
        $sink = WormAuditSinkFactory::fromConfig([]);
        $this->assertInstanceOf(NullWormAuditSink::class, $sink);

        $service = new PlatformAuditService($this->connection, $sink, true);
        $service->record('platform.audit.disabled', null, null, null, null, [], true, [
            'createdAt' => '2026-05-16 01:00:00',
        ]);

        $count = $this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0);
        $this->assertSame(1, (int)$count);
        $this->assertFileDoesNotExist($this->wormFile);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readWormRecords(): array
    {
        $contents = file_get_contents($this->wormFile);
        $this->assertIsString($contents);
        $records = [];
        foreach (array_filter(explode(PHP_EOL, trim($contents))) as $line) {
            $record = json_decode($line, true);
            $this->assertIsArray($record);
            $records[] = $record;
        }

        return $records;
    }
}
