<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupDownloadService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class BackupDownloadServiceTest extends TestCase
{
    private string $workRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workRoot = TMP . 'backup-download-test-' . uniqid('', true);
        mkdir($this->workRoot, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workRoot . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->workRoot);
        parent::tearDown();
    }

    public function testStageVerifiesIntegrityAndProducesSafeFilename(): void
    {
        $payload = '{"ciphertext":"encrypted"}';
        $storage = new DownloadArchiveStorage($this->workRoot, $payload);
        $backupId = '11111111-1111-4111-8111-111111111111';

        $download = (new BackupDownloadService())->stage([
            'id' => $backupId,
            'backup_type' => 'json',
            'object_uri' => 'backup://tenants/acme/archive',
            'object_size_bytes' => strlen($payload),
            'object_sha256' => hash('sha256', $payload),
        ], $storage, 'Acme Kingdom');

        $this->assertFileExists($download['path']);
        $this->assertSame($payload, file_get_contents($download['path']));
        $this->assertSame(
            'acme-kingdom-' . $backupId . '.json.gz.enc',
            $download['filename'],
        );
    }

    public function testStageDeletesTemporaryFileWhenChecksumDoesNotMatch(): void
    {
        $storage = new DownloadArchiveStorage($this->workRoot, 'encrypted');
        $backupId = '11111111-1111-4111-8111-111111111111';

        try {
            (new BackupDownloadService())->stage([
                'id' => $backupId,
                'backup_type' => 'json',
                'object_uri' => 'backup://tenants/acme/archive',
                'object_size_bytes' => 9,
                'object_sha256' => str_repeat('a', 64),
            ], $storage, 'acme');
            $this->fail('Expected checksum mismatch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }

        $this->assertSame([], glob($this->workRoot . DIRECTORY_SEPARATOR . '*') ?: []);
    }

    public function testStagePreservesLegacyKmpBackupWithoutHistoricalChecksum(): void
    {
        $payload = '{"legacy":"encrypted"}';
        $storage = new DownloadArchiveStorage($this->workRoot, $payload);
        $backupId = '11111111-1111-4111-8111-111111111111';

        $download = (new BackupDownloadService())->stage([
            'id' => $backupId,
            'backup_type' => 'kmpbackup_json',
            'object_uri' => 'legacy-archive.kmpbackup',
            'object_size_bytes' => null,
            'object_sha256' => null,
        ], $storage, 'Legacy Kingdom');

        $this->assertSame(
            'legacy-kingdom-' . $backupId . '.kmpbackup',
            $download['filename'],
        );
        $this->assertSame($payload, file_get_contents($download['path']));
    }

    public function testConcurrentDownloadsUseDifferentStagingPaths(): void
    {
        $payload = '{"ciphertext":"encrypted"}';
        $storage = new DownloadArchiveStorage($this->workRoot, $payload);
        $backup = [
            'id' => '11111111-1111-4111-8111-111111111111',
            'backup_type' => 'json',
            'object_uri' => 'backup://tenants/acme/archive',
            'object_size_bytes' => strlen($payload),
            'object_sha256' => hash('sha256', $payload),
        ];
        $service = new BackupDownloadService();

        $first = $service->stage($backup, $storage, 'acme');
        $second = $service->stage($backup, $storage, 'acme');

        $this->assertNotSame($first['path'], $second['path']);
        $this->assertFileExists($first['path']);
        $this->assertFileExists($second['path']);
    }
}
