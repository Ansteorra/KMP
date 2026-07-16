<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupObjectStorage;
use App\Services\BackupStorageService;
use App\Test\TestCase\BaseTestCase;

class BackupObjectStorageTest extends BaseTestCase
{
    public function testStoreHandlesAdapterClosingUploadStream(): void
    {
        $storage = new class extends BackupStorageService {
            public string $writtenPath = '';

            public function __construct()
            {
            }

            public function writeStream(string $filename, mixed $stream): void
            {
                $this->writtenPath = $filename;
                fclose($stream);
            }
        };
        $workRoot = TMP . 'backup-object-storage-test-' . bin2hex(random_bytes(4));
        $encryptedPath = $workRoot . DIRECTORY_SEPARATOR . 'source.enc';
        mkdir($workRoot, 0700, true);
        file_put_contents($encryptedPath, 'encrypted-backup');
        $objectPath = 'tenants/ansteorra/12345678-1234-1234-1234-123456789abc.json.gz.enc';

        try {
            $stored = (new BackupObjectStorage($storage, $workRoot))->store(
                $objectPath,
                $encryptedPath,
            );

            $this->assertSame($objectPath, $storage->writtenPath);
            $this->assertSame('backup://' . $objectPath, $stored->uri);
            $this->assertFileDoesNotExist($encryptedPath);
        } finally {
            if (is_file($encryptedPath)) {
                unlink($encryptedPath);
            }
            if (is_dir($workRoot)) {
                rmdir($workRoot);
            }
        }
    }
}
