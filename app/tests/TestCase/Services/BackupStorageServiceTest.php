<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\BackupStorageService;
use App\Test\TestCase\BaseTestCase;
use Exception;

class BackupStorageServiceTest extends BaseTestCase
{
    protected ?BackupStorageService $service = null;
    private array $testFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        try {
            $this->service = new BackupStorageService();
        } catch (Exception $e) {
            $this->markTestSkipped('BackupStorageService initialization failed: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test files we created
        if ($this->service !== null) {
            foreach ($this->testFiles as $filename) {
                try {
                    if ($this->service->exists($filename)) {
                        $this->service->delete($filename);
                    }
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }
        parent::tearDown();
    }

    private function trackFile(string $filename): void
    {
        $this->testFiles[] = $filename;
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(BackupStorageService::class, $this->service);
    }

    public function testGetAdapterType(): void
    {
        $type = $this->service->getAdapterType();
        $this->assertIsString($type);
        $this->assertContains($type, ['local', 'azure', 's3']);
    }

    public function testWriteAndRead(): void
    {
        $filename = 'test_backup_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $data = 'CREATE TABLE test (id INT PRIMARY KEY);';
        $this->service->write($filename, $data);

        $this->assertTrue($this->service->exists($filename));

        $readData = $this->service->read($filename);
        $this->assertEquals($data, $readData);
    }

    public function testExists(): void
    {
        $filename = 'test_exists_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $this->assertFalse($this->service->exists($filename));

        $this->service->write($filename, 'test data');
        $this->assertTrue($this->service->exists($filename));
    }

    public function testDelete(): void
    {
        $filename = 'test_delete_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $this->service->write($filename, 'to be deleted');
        $this->assertTrue($this->service->exists($filename));

        $this->service->delete($filename);
        $this->assertFalse($this->service->exists($filename));
    }

    public function testListFilesIncludesWrittenFile(): void
    {
        $filename = 'test_list_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $this->service->write($filename, 'list test');

        $files = $this->service->listFiles();
        $this->assertIsArray($files);
        $this->assertContains($filename, $files);
    }

    public function testListFilesReturnsSortedArray(): void
    {
        $fileA = 'test_sort_a_' . uniqid() . '.sql';
        $fileB = 'test_sort_b_' . uniqid() . '.sql';
        $this->trackFile($fileA);
        $this->trackFile($fileB);

        $this->service->write($fileB, 'b data');
        $this->service->write($fileA, 'a data');

        $files = $this->service->listFiles();
        // Verify files are sorted
        $sortedFiles = $files;
        sort($sortedFiles);
        $this->assertEquals($sortedFiles, $files);
    }

    public function testWriteOverwritesExistingFile(): void
    {
        $filename = 'test_overwrite_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $this->service->write($filename, 'original');
        $this->service->write($filename, 'updated');

        $data = $this->service->read($filename);
        $this->assertEquals('updated', $data);
    }

    public function testWriteEmptyContent(): void
    {
        $filename = 'test_empty_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $this->service->write($filename, '');

        $this->assertTrue($this->service->exists($filename));
        $data = $this->service->read($filename);
        $this->assertEquals('', $data);
    }

    public function testWriteLargeContent(): void
    {
        $filename = 'test_large_' . uniqid() . '.sql';
        $this->trackFile($filename);

        $data = str_repeat('INSERT INTO test VALUES (1);' . PHP_EOL, 1000);
        $this->service->write($filename, $data);

        $readData = $this->service->read($filename);
        $this->assertEquals($data, $readData);
    }

    public function testDefaultAdapterIsLocal(): void
    {
        // In dev environment without cloud config, should default to local
        $type = $this->service->getAdapterType();
        $this->assertEquals('local', $type);
    }
}
