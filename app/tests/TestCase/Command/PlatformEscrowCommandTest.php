<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class PlatformEscrowCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetPlatformConnection();
        $this->createEscrowVerificationsTable();
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        parent::tearDown();
    }

    public function testRecordVerificationCommandStoresRedactedMetadata(): void
    {
        $this->exec(sprintf(
            'platform escrow record-verification --key-name tenant.demo.kek --key-version v1 '
            . '--threshold 3 --share-count 5 --status verified '
            . '--metadata %s --notes %s',
            escapeshellarg('{"custodians":5,"share_plaintext":"never-persist"}'),
            escapeshellarg('Quarterly ceremony. kek=never-persist'),
        ));

        $this->assertExitSuccess();
        $this->assertOutputContains('Recorded escrow verification');

        $row = ConnectionManager::get('platform')
            ->execute('SELECT key_name, metadata, notes FROM escrow_verifications')
            ->fetch('assoc');

        $this->assertIsArray($row);
        $this->assertSame('tenant.demo.kek', $row['key_name']);
        $this->assertStringContainsString('[REDACTED]', (string)$row['metadata']);
        $this->assertStringNotContainsString('never-persist', (string)$row['metadata']);
        $this->assertStringNotContainsString('never-persist', (string)$row['notes']);
    }

    private function resetPlatformConnection(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
    }

    private function createEscrowVerificationsTable(): void
    {
        ConnectionManager::get('platform')->execute(
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
}
