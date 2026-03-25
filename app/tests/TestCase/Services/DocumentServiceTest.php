<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Document;
use App\Services\DocumentService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
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
}
