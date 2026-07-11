<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\LocalTenantBackupStorage;
use Cake\TestSuite\TestCase;

class LocalTenantBackupStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = TMP . 'local-tenant-backup-storage-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    public function testRetrievesNewArchiveWithMaximumSafeBackupId(): void
    {
        $backupId = str_repeat('a', 128);
        $objectDirectory = $this->root . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'example';
        mkdir($objectDirectory, 0700, true);
        file_put_contents(
            $objectDirectory . DIRECTORY_SEPARATOR . $backupId . '.json.gz.enc',
            'encrypted backup',
        );
        $destination = $this->root . DIRECTORY_SEPARATOR . 'restore' . DIRECTORY_SEPARATOR . 'backup.json.gz.enc';
        $storage = new LocalTenantBackupStorage($this->root, true);

        $storedObject = $storage->retrieve(
            'local://example/' . $backupId . '.json.gz.enc',
            $destination,
        );

        $this->assertSame('encrypted backup', file_get_contents($destination));
        $this->assertSame(strlen('encrypted backup'), $storedObject->sizeBytes);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            unlink($path);
        }
        rmdir($directory);
    }
}
