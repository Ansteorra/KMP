<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Escrow;

use App\Services\Escrow\EscrowCeremonyRequest;
use App\Services\Escrow\EscrowVerificationRequest;
use App\Services\Escrow\NonProductionDeterministicKekEscrowSplitter;
use App\Services\Escrow\PlatformEscrowCeremonyTracker;
use App\Services\Escrow\PlatformEscrowVerificationRecorder;
use App\Services\Secrets\SensitiveString;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use RuntimeException;

class PlatformEscrowVerificationRecorderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->createEscrowVerificationsTable();
        $this->createEscrowCeremonyTables();
    }

    public function testRecordVerificationPersistsMetadataOnly(): void
    {
        $recorder = new PlatformEscrowVerificationRecorder($this->connection);
        $record = $recorder->recordVerification(new EscrowVerificationRequest(
            null,
            null,
            'secrets-db-driver-kek',
            '2026-q2',
            3,
            5,
            new DateTimeImmutable('2026-05-16 12:00:00 UTC'),
            null,
            'verified',
            [
                'officer_count' => 5,
                'raw_kek' => 'do-not-store-this-kek',
                'nested' => [
                    'share_plaintext' => 'do-not-store-this-share',
                    'location' => 'sealed envelopes verified in safe',
                ],
            ],
            'Quarterly verification complete. share=do-not-store-this-share',
        ));

        $row = $this->connection
            ->execute('SELECT * FROM escrow_verifications WHERE id = ?', [$record['id']])
            ->fetch('assoc');

        $this->assertIsArray($row);
        $this->assertSame('secrets-db-driver-kek', $row['key_name']);
        $this->assertSame(3, (int)$row['threshold']);
        $this->assertSame(5, (int)$row['share_count']);
        $metadata = json_decode((string)$row['metadata'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('[REDACTED]', $metadata['raw_kek']);
        $this->assertSame('[REDACTED]', $metadata['nested']['share_plaintext']);
        $this->assertSame('sealed envelopes verified in safe', $metadata['nested']['location']);
        $this->assertStringNotContainsString('do-not-store-this-kek', (string)$row['metadata']);
        $this->assertStringNotContainsString('do-not-store-this-share', (string)$row['metadata']);
        $this->assertStringNotContainsString('do-not-store-this-share', (string)$row['notes']);
    }

    public function testPlaceholderSplitterRefusesProductionUse(): void
    {
        $splitter = new NonProductionDeterministicKekEscrowSplitter('production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forbidden in production');

        $splitter->split(new SensitiveString('test-kek'), 3, 5);
    }

    public function testRecordCeremonyStoresOnlyHashedEnvelopeLabels(): void
    {
        $tracker = new PlatformEscrowCeremonyTracker($this->connection);
        $ceremony = $tracker->recordCeremony(new EscrowCeremonyRequest(
            null,
            'tenant.demo.kek',
            'v1',
            3,
            5,
            'sealed',
            ['share_plaintext' => 'do-not-store-this-share'],
            'Created sealed envelopes. kek=do-not-store-this-kek',
            null,
            [[
                'share_index' => 1,
                'custodian_label' => 'Officer One',
                'envelope_label' => 'Envelope A',
                'metadata' => ['key_material' => 'do-not-store-this-kek'],
            ]],
        ));

        $ceremonyRow = $this->connection
            ->execute('SELECT metadata, notes FROM escrow_ceremonies WHERE id = ?', [$ceremony['id']])
            ->fetch('assoc');
        $envelopeRow = $this->connection
            ->execute('SELECT * FROM escrow_share_envelopes WHERE escrow_ceremony_id = ?', [$ceremony['id']])
            ->fetch('assoc');

        $this->assertIsArray($ceremonyRow);
        $this->assertIsArray($envelopeRow);
        $this->assertStringContainsString('[REDACTED]', (string)$ceremonyRow['metadata']);
        $this->assertStringNotContainsString('do-not-store-this-share', (string)$ceremonyRow['metadata']);
        $this->assertStringNotContainsString('do-not-store-this-kek', (string)$ceremonyRow['notes']);
        $this->assertSame(hash('sha256', 'Officer One'), $envelopeRow['custodian_label_hash']);
        $this->assertStringNotContainsString('Officer One', implode(' ', $envelopeRow));
        $this->assertStringContainsString('[REDACTED]', (string)$envelopeRow['metadata']);
    }

    private function createEscrowVerificationsTable(): void
    {
        $this->connection->execute(
            'CREATE TABLE escrow_verifications (
                id TEXT PRIMARY KEY,
                escrow_ceremony_id TEXT NULL,
                tenant_id TEXT NULL,
                key_name TEXT NOT NULL,
                key_version TEXT NOT NULL,
                threshold INTEGER NOT NULL,
                share_count INTEGER NOT NULL,
                verified_at TEXT NOT NULL,
                verified_by_platform_user_id TEXT NULL,
                status TEXT NOT NULL,
                metadata TEXT NULL,
                notes TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }

    private function createEscrowCeremonyTables(): void
    {
        $this->connection->execute(
            'CREATE TABLE escrow_ceremonies (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                key_name TEXT NOT NULL,
                key_version TEXT NOT NULL,
                threshold INTEGER NOT NULL,
                share_count INTEGER NOT NULL,
                status TEXT NOT NULL,
                metadata TEXT NULL,
                notes TEXT NULL,
                created_by_platform_user_id TEXT NULL,
                completed_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE escrow_share_envelopes (
                id TEXT PRIMARY KEY,
                escrow_ceremony_id TEXT NOT NULL,
                share_index INTEGER NOT NULL,
                custodian_label_hash TEXT NOT NULL,
                envelope_label_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                verified_at TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }
}
