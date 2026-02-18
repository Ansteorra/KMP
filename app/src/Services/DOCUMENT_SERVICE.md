# DocumentService

## Overview

The `DocumentService` is a centralized service for managing document uploads, storage, and retrieval throughout the KMP application. It provides a consistent interface for all document-related operations and abstracts storage implementation details from consuming code.

## Purpose

- **Centralized Logic**: All document handling code in one place
- **Storage Abstraction**: Change storage strategy (local → S3) without touching consumer code
- **Consistency**: Same validation and error handling everywhere
- **Security**: Centralized security checks for file access
- **Maintenance**: Easy to update and extend

## Architecture

### Storage Pattern

Documents are stored using a polymorphic association pattern:
- `entity_type`: String identifying the owning entity (e.g., 'Waivers.WaiverTypes')
- `entity_id`: Foreign key to the owning entity
- `file_path`: Relative path to the stored file
- `storage_adapter`: Storage backend identifier ('local', 's3', etc.)

### File Storage Structure

```
/app/images/uploaded/
  ├── waiver-templates/
  │   ├── doc_12345.pdf
  │   └── doc_67890.pdf
  ├── member-photos/
  │   └── doc_11111.jpg
  └── [other subdirectories]
```

## Usage

### In Controllers

```php
use App\Services\DocumentService;

class MyController extends AppController
{
    private DocumentService $DocumentService;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->DocumentService = new DocumentService();
    }
}
```

### Creating Documents

#### Basic Upload

```php
// From a form upload
$file = $this->request->getData('uploaded_file');

$result = $this->DocumentService->createDocument(
    $file,
    'MyPlugin.MyModel',
    $entityId,
    $this->Authentication->getIdentity()->id
);

if ($result->success) {
    $documentId = $result->data;
    // Save document_id to your entity
} else {
    $this->Flash->error($result->reason);
}
```

#### Upload with Metadata

```php
$result = $this->DocumentService->createDocument(
    $file,
    'Waivers.WaiverTypes',
    $waiverType->id,
    $uploaderId,
    ['type' => 'waiver_template', 'source' => 'admin_upload'], // metadata
    'waiver-templates', // subdirectory
    ['pdf', 'doc', 'docx'] // allowed extensions
);
```

#### Two-Step Creation (Entity ID Not Available)

When you need to save the parent entity first to get its ID:

```php
// 1. Create document with temporary entity_id
$result = $this->DocumentService->createDocument(
    $file,
    'MyPlugin.MyModel',
    0, // Temporary placeholder
    $uploaderId
);

if ($result->success) {
    // 2. Save parent entity
    if ($this->MyModel->save($entity)) {
        // 3. Update document's entity_id
        $this->DocumentService->updateDocumentEntityId(
            $result->data,
            $entity->id
        );
    }
}
```

### Downloading Documents

```php
public function download($id)
{
    $entity = $this->MyModel->get($id, contain: ['Documents']);
    $this->Authorization->authorize($entity, 'view');
    
    $response = $this->DocumentService->getDocumentDownloadResponse(
        $entity->document,
        'custom_filename.pdf' // Optional custom name
    );
    
    if ($response === null) {
        $this->Flash->error(__('File not found.'));
        return $this->redirect(['action' => 'view', $id]);
    }
    
    return $response;
}
```

### Deleting Documents

```php
$result = $this->DocumentService->deleteDocument($documentId);

if ($result->success) {
    $this->Flash->success(__('Document deleted.'));
} else {
    $this->Flash->error($result->reason);
}
```

## API Reference

### `createDocument()`

Creates a document record and stores the physical file.

**Parameters:**
- `UploadedFile $file` - The uploaded file object
- `string $entityType` - Entity type (e.g., 'Waivers.WaiverTypes')
- `int $entityId` - Entity ID (use 0 if not yet available)
- `int $uploadedBy` - Member ID of uploader
- `array $metadata` - Optional metadata array
- `string $subDirectory` - Optional subdirectory for storage
- `array $allowedExtensions` - Allowed file extensions (default: ['pdf'])

**Returns:** `ServiceResult`
- `success`: true/false
- `data`: Document ID on success
- `reason`: Error message on failure

**Example:**
```php
$result = $this->DocumentService->createDocument(
    $uploadedFile,
    'Waivers.WaiverTypes',
    $waiverTypeId,
    $memberId,
    ['category' => 'template'],
    'waiver-templates',
    ['pdf']
);
```

### `getDocumentDownloadResponse()`

Gets a Response object for downloading a document.

**Parameters:**
- `Document $document` - The document entity
- `string|null $downloadName` - Optional custom filename for download

**Returns:** `Response|null`
- `Response` object configured for file download
- `null` if file not found

**Example:**
```php
$response = $this->DocumentService->getDocumentDownloadResponse(
    $document,
    'custom_name.pdf'
);
```

### `updateDocumentEntityId()`

Updates the entity_id of an existing document.

**Parameters:**
- `int $documentId` - Document ID to update
- `int $entityId` - New entity ID

**Returns:** `ServiceResult`
- `success`: true/false
- `reason`: Error message on failure

**Example:**
```php
$result = $this->DocumentService->updateDocumentEntityId(
    $documentId,
    $newEntityId
);
```

### `deleteDocument()`

Deletes a document record and its physical file.

**Parameters:**
- `int $documentId` - Document ID to delete

**Returns:** `ServiceResult`
- `success`: true/false
- `reason`: Error message on failure

**Example:**
```php
$result = $this->DocumentService->deleteDocument($documentId);
```

## Error Handling

The service uses the `ServiceResult` pattern for consistent error handling:

```php
$result = $this->DocumentService->createDocument(...);

if ($result->success) {
    // Success - use $result->data
    $documentId = $result->data;
} else {
    // Failure - display $result->reason
    $this->Flash->error($result->reason);
}
```

### Common Error Messages

- "Invalid file type. Allowed types: pdf, doc"
- "Failed to create storage directory."
- "Failed to upload file: [exception message]"
- "Failed to save document record."
- "File not found"

## Security Considerations

1. **Path Validation**: Uses `realpath()` to prevent directory traversal attacks
2. **File Extension Validation**: Server-side validation of allowed extensions
3. **File Size Limits**: Respects PHP upload limits
4. **Authorization**: Consumers must implement authorization checks
5. **Checksum**: SHA-256 checksums stored for integrity verification

## Storage Adapters

### Supported Storage Adapters

DocumentService currently supports these adapters via `Documents.storage.adapter`:

- `local` — Files stored in `/app/images/uploaded/`
- `s3` — Files stored in an S3-compatible bucket (AWS S3, MinIO, etc)
- `azure` — Files stored in Azure Blob Storage

Cloud adapters are accessed through Flysystem, so upload/download/delete behavior remains
consistent for consumer code regardless of where files are stored.

## Integration Examples

### Waivers Plugin

The Waivers plugin uses DocumentService for template uploads:

```php
// In WaiverTypesController::_handleTemplateUpload()
$result = $this->DocumentService->createDocument(
    $file,
    'Waivers.WaiverTypes',
    $waiverType->id ?? 0,
    $this->Authentication->getIdentity()->id,
    ['type' => 'waiver_template'],
    'waiver-templates',
    ['pdf']
);

if ($result->success) {
    return ['document_id' => $result->data, 'template_path' => null];
}
```

### Future Integrations

Potential uses for DocumentService:

- **Members**: Profile photos, resume uploads
- **Awards**: Supporting documentation
- **Officers**: Position descriptions, handbooks
- **Events**: Flyers, promotional materials
- **Reports**: Generated PDF reports
- **Forms**: Filled form submissions

## Testing

### Unit Tests

Test the service in isolation:

```php
public function testCreateDocument()
{
    $mockFile = $this->createMock(UploadedFile::class);
    $mockFile->method('getSize')->willReturn(1024);
    $mockFile->method('getError')->willReturn(UPLOAD_ERR_OK);
    
    $service = new DocumentService();
    $result = $service->createDocument(
        $mockFile,
        'Test.Entity',
        1,
        1
    );
    
    $this->assertTrue($result->success);
}
```

### Integration Tests

Test with actual file uploads:

```php
public function testFileUploadAndDownload()
{
    // Upload file
    $this->post('/my-entity/add', [
        'name' => 'Test',
        'file' => [
            'tmp_name' => TMP . 'test.pdf',
            'name' => 'test.pdf',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK
        ]
    ]);
    
    $this->assertResponseSuccess();
    
    // Download file
    $this->get('/my-entity/download/1');
    $this->assertResponseOk();
    $this->assertHeader('Content-Type', 'application/pdf');
}
```

## Logging

The service logs important events:

- **Info**: Document creation, deletion
- **Warning**: Physical file deletion failures
- **Error**: Upload failures, missing files, save failures

Example log entries:

```
[info] Document created successfully {"document_id":123,"entity_type":"Waivers.WaiverTypes"}
[error] Failed to move uploaded file: Permission denied
[warning] Failed to delete physical file {"document_id":45,"path":"/path/to/file"}
```

## Migration Guide

### From Direct File Handling to DocumentService

**Before:**
```php
$file = $this->request->getData('file');
$path = WWW_ROOT . '../uploads/' . $file->getClientFilename();
$file->moveTo($path);
$entity->file_path = $path;
```

**After:**
```php
$result = $this->DocumentService->createDocument(
    $this->request->getData('file'),
    'MyPlugin.MyModel',
    $entity->id,
    $userId
);

if ($result->success) {
    $entity->document_id = $result->data;
}
```

## Best Practices

1. **Always check ServiceResult.success** before using data
2. **Use subdirectories** to organize files by type
3. **Specify allowed extensions** explicitly
4. **Include meaningful metadata** for future reference
5. **Implement authorization** in controllers before download
6. **Handle null responses** from getDocumentDownloadResponse()
7. **Clean up orphaned documents** when parent entities are deleted
8. **Use descriptive download names** for better user experience

## Troubleshooting

### "Failed to create storage directory"
- Check filesystem permissions on `/app/images/uploaded/`
- Ensure web server has write access

### "File not found" on download
- Verify file exists at expected path
- Check database `file_path` matches physical location
- Look for filesystem permission issues

### "Invalid file type"
- Ensure allowed extensions include the uploaded file type
- Check both client-side (HTML5) and server-side validation

### Documents not appearing
- Verify `entity_type` and `entity_id` are correct
- Check that association is properly configured in model
- Use `contain: ['Documents']` when fetching entities
