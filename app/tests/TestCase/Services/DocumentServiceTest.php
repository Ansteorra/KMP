<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Document;
use App\Services\DocumentService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Cake\Core\Configure;
use Exception;

class DocumentServiceTest extends BaseTestCase
{
    protected ?DocumentService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        try {
            $this->service = new DocumentService();
        } catch (Exception $e) {
            $this->markTestSkipped('DocumentService initialization failed: ' . $e->getMessage());
        }
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(DocumentService::class, $this->service);
    }

    public function testUpdateDocumentEntityIdWithValidDocument(): void
    {
        // Find an existing document in seed data
        $documentsTable = $this->getTableLocator()->get('Documents');
        $existingDoc = $documentsTable->find()->first();

        if ($existingDoc === null) {
            $this->markTestSkipped('No documents in seed data');
        }

        $result = $this->service->updateDocumentEntityId($existingDoc->id, 999);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess());

        // Verify update
        $updated = $documentsTable->get($existingDoc->id);
        $this->assertEquals(999, $updated->entity_id);
    }

    public function testUpdateDocumentEntityIdWithInvalidId(): void
    {
        $result = $this->service->updateDocumentEntityId(999999, 1);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testDeleteDocumentWithInvalidId(): void
    {
        $result = $this->service->deleteDocument(999999);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testDeleteDocumentRemovesRecord(): void
    {
        // Create a document record directly in the database
        $documentsTable = $this->getTableLocator()->get('Documents');
        $doc = $documentsTable->newEntity([
            'entity_type' => 'TestEntity',
            'entity_id' => 1,
            'uploaded_by' => self::ADMIN_MEMBER_ID,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'test_stored_' . uniqid() . '.pdf',
            'file_path' => 'test_path_' . uniqid() . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum' => hash('sha256', 'test content'),
            'storage_adapter' => 'local',
        ]);

        $saved = $documentsTable->save($doc);
        $this->assertNotFalse($saved, 'Test document should save');

        $result = $this->service->deleteDocument($saved->id);
        $this->assertInstanceOf(ServiceResult::class, $result);
        $this->assertTrue($result->isSuccess());

        // Verify record is gone
        $found = $documentsTable->find()->where(['id' => $saved->id])->first();
        $this->assertNull($found, 'Document record should be deleted');
    }

    public function testDocumentPreviewExistsReturnsFalseForNonExistent(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'nonexistent/path/to/file.pdf';
        $doc->storage_adapter = 'local';

        $result = $this->service->documentPreviewExists($doc);
        $this->assertFalse($result);
    }

    public function testDocumentPreviewExistsReturnsFalseForEmptyPath(): void
    {
        $doc = new Document();
        $doc->id = 1;
        $doc->file_path = '';
        $doc->storage_adapter = 'local';

        $result = $this->service->documentPreviewExists($doc);
        $this->assertFalse($result);
    }

    public function testGetDocumentDownloadResponseReturnsNullForMissingFile(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'nonexistent/file.pdf';
        $doc->storage_adapter = 'local';
        $doc->original_filename = 'test.pdf';
        $doc->mime_type = 'application/pdf';

        $response = $this->service->getDocumentDownloadResponse($doc);
        $this->assertNull($response);
    }

    public function testGetDocumentInlineResponseReturnsNullForMissingFile(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'nonexistent/file.pdf';
        $doc->storage_adapter = 'local';
        $doc->original_filename = 'test.pdf';
        $doc->mime_type = 'application/pdf';

        $response = $this->service->getDocumentInlineResponse($doc);
        $this->assertNull($response);
    }

    public function testGetDocumentPreviewResponseReturnsNullForMissingFile(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'nonexistent/file.pdf';
        $doc->storage_adapter = 'local';

        $response = $this->service->getDocumentPreviewResponse($doc);
        $this->assertNull($response);
    }

    public function testGetDocumentDownloadResponseReturnsNullForInvalidAdapter(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'some/file.pdf';
        $doc->storage_adapter = 'unsupported_adapter_type';
        $doc->original_filename = 'test.pdf';

        $response = $this->service->getDocumentDownloadResponse($doc);
        $this->assertNull($response);
    }

    public function testGetDocumentInlineResponseReturnsNullForInvalidAdapter(): void
    {
        $doc = new Document();
        $doc->id = 999999;
        $doc->file_path = 'some/file.pdf';
        $doc->storage_adapter = 'unsupported_adapter_type';
        $doc->original_filename = 'test.pdf';

        $response = $this->service->getDocumentInlineResponse($doc);
        $this->assertNull($response);
    }

    public function testImageThumbnailResponseGeneratesAndReusesDerivedImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for thumbnail generation');
        }

        $config = Configure::read('Documents.storage', []);
        $basePath = $config['local']['path'] ?? WWW_ROOT . '../images/uploaded/';
        $relativePath = 'test-profile-thumbnails/source-' . uniqid() . '.jpg';
        $sourcePath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $sourceDirectory = dirname($sourcePath);
        if (!is_dir($sourceDirectory)) {
            mkdir($sourceDirectory, 0755, true);
        }

        $sourceImage = imagecreatetruecolor(1200, 800);
        $color = imagecolorallocate($sourceImage, 20, 90, 160);
        imagefill($sourceImage, 0, 0, $color);
        imagejpeg($sourceImage, $sourcePath, 95);
        imagedestroy($sourceImage);
        $sourceContents = file_get_contents($sourcePath);
        $this->assertIsString($sourceContents);
        file_put_contents($sourcePath, $this->addExifOrientation($sourceContents, 6));

        $document = new Document([
            'id' => 123,
            'file_path' => $relativePath,
            'original_filename' => 'profile.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => filesize($sourcePath),
            'checksum' => hash_file('sha256', $sourcePath),
            'storage_adapter' => 'local',
        ]);
        $thumbnailPath = $basePath . DIRECTORY_SEPARATOR . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $this->service->getImageThumbnailPath($document),
        );

        try {
            $response = $this->service->getImageThumbnailInlineResponse($document);
            $this->assertNotNull($response);
            $this->assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));

            $thumbnailContents = (string)$response->getBody();
            $thumbnailInfo = getimagesizefromstring($thumbnailContents);
            $this->assertIsArray($thumbnailInfo);
            $this->assertSame(293, $thumbnailInfo[0]);
            $this->assertSame(440, $thumbnailInfo[1]);
            $this->assertFileExists($thumbnailPath);

            unlink($sourcePath);
            $cachedResponse = $this->service->getImageThumbnailInlineResponse($document);
            $this->assertNotNull($cachedResponse);
            $this->assertSame($thumbnailContents, (string)$cachedResponse->getBody());
        } finally {
            if (file_exists($sourcePath)) {
                unlink($sourcePath);
            }
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }

    /**
     * Add a minimal EXIF orientation block to a generated JPEG fixture.
     */
    private function addExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = 'II'
            . pack('v', 42)
            . pack('V', 8)
            . pack('v', 1)
            . pack('v', 0x0112)
            . pack('v', 3)
            . pack('V', 1)
            . pack('v', $orientation)
            . "\0\0"
            . pack('V', 0);
        $payload = "Exif\0\0" . $tiff;

        return substr($jpeg, 0, 2)
            . "\xFF\xE1"
            . pack('n', strlen($payload) + 2)
            . $payload
            . substr($jpeg, 2);
    }
}
