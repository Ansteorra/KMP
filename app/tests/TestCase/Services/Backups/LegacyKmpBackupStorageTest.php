<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\LegacyKmpBackupStorage;
use App\Services\BackupStorageService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class LegacyKmpBackupStorageTest extends TestCase
{
    private string $workRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workRoot = TMP . 'legacy-backup-storage-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workRoot . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->workRoot)) {
            rmdir($this->workRoot);
        }
        parent::tearDown();
    }

    public function testRetrieveStagesLegacyArchiveWithVerifiedMetadata(): void
    {
        $payload = '{"legacy":"archive"}';
        $source = fopen('php://temp', 'w+b');
        fwrite($source, $payload);
        rewind($source);
        $storage = $this->createMock(BackupStorageService::class);
        $storage->expects($this->once())
            ->method('readStream')
            ->with('legacy-archive.kmpbackup')
            ->willReturn($source);
        $adapter = new LegacyKmpBackupStorage($storage, $this->workRoot);
        $destination = $adapter->workPath('backup-1', '.download.kmpbackup');

        $stored = $adapter->retrieve('legacy-archive.kmpbackup', $destination);

        $this->assertSame(strlen($payload), $stored->sizeBytes);
        $this->assertSame(hash('sha256', $payload), $stored->sha256);
        $this->assertSame($payload, file_get_contents($destination));
    }

    public function testDeleteRejectsTraversalObjectName(): void
    {
        $storage = $this->createMock(BackupStorageService::class);
        $storage->expects($this->never())->method('delete');
        $adapter = new LegacyKmpBackupStorage($storage, $this->workRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe legacy backup object name');

        $adapter->delete('../legacy-archive.kmpbackup');
    }
}
