<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Document;
use App\Model\Table\DocumentsTable;
use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobServiceClient;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Document Service
 *
 * Centralized service for document management including file uploads, storage, and retrieval.
 * This service uses Flysystem to abstract storage operations and supports multiple backends
 * including local filesystem and Azure Blob Storage.
 *
 * ## Key Responsibilities
 *
 * - **File Upload Processing**: Validates and processes uploaded files
 * - **Document Creation**: Creates document records with metadata
 * - **File Storage**: Handles physical file storage using Flysystem (local/Azure/etc)
 * - **File Retrieval**: Provides file download responses
 * - **Storage Abstraction**: Uses Flysystem for consistent API across storage backends
 *
 * ## Configuration
 *
 * Set storage configuration in `config/app_local.php`:
 *
 * ```php
 * 'Documents' => [
 *     'storage' => [
 *         'adapter' => 'local', // or 'azure'
 *         'path' => ROOT . DS . 'images' . DS . 'uploaded',
 *         // For Azure:
 *         // 'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
 *         // 'container' => 'documents',
 *         // 'prefix' => '', // Optional prefix for all paths
 *     ],
 * ],
 * ```
 *
 * ## Usage in Controllers
 *
 * ```php
 * // In controller initialize
 * $this->DocumentService = new DocumentService();
 *
 * // Create document from upload
 * $result = $this->DocumentService->createDocument(
 *     $uploadedFile,
 *     'Waivers.WaiverTypes',
 *     $waiverType->id,
 *     $this->Authentication->getIdentity()->id,
 *     ['type' => 'waiver_template']
 * );
 *
 * if ($result->success) {
 *     $documentId = $result->data;
 * }
 *
 * // Download document
 * $document = $this->fetchTable('Documents')->get($documentId);
 * $response = $this->DocumentService->getDocumentDownloadResponse(
 *     $document,
 *     'template.pdf'
 * );
 * return $response;
 * ```
 *
 * ## Storage Strategy
 *
 * Uses Flysystem to abstract storage operations. Supports:
 * - Local filesystem storage (default)
 * - Azure Blob Storage
 *
 * Storage adapter is selected based on configuration.
 *
 * @see \App\Model\Table\DocumentsTable
 * @see \App\Model\Entity\Document
 * @see \League\Flysystem\Filesystem
 */
class DocumentService
{
    use LocatorAwareTrait;

    /**
     * Base storage path for uploaded documents (for local adapter)
     */
    private const STORAGE_BASE_PATH = 'images/uploaded/';

    /**
     * Flysystem filesystem instance
     *
     * @var \League\Flysystem\Filesystem
     */
    private FlysystemFilesystem $filesystem;

    /**
     * Storage adapter type ('local' or 'azure')
     *
     * @var string
     */
    private string $adapter;

    /**
     * Base path for local storage
     *
     * @var string|null
     */
    private ?string $localBasePath = null;

    /**
     * Documents table instance
     *
     * @var \App\Model\Table\DocumentsTable
     */
    private DocumentsTable $Documents;

    /**
     * Constructor - initializes Flysystem with configured adapter
     */
    public function __construct()
    {
        $this->Documents = $this->fetchTable('Documents');
        $this->initializeFilesystem();
    }

    /**
     * Initialize Flysystem with the configured storage adapter
     *
     * @return void
     */
    private function initializeFilesystem(): void
    {
        $config = Configure::read('Documents.storage', []);
        $this->adapter = $config['adapter'] ?? 'local';

        if ($this->adapter === 'azure') {
            // Azure Blob Storage configuration
            $azureConfig = $config['azure'] ?? [];
            $connectionString = $azureConfig['connectionString'] ?? null;
            $container = $azureConfig['container'] ?? 'documents';
            $prefix = $azureConfig['prefix'] ?? '';

            if (empty($connectionString)) {
                Log::error(
                    'Azure storage connection string not configured. ' .
                        'Set AZURE_STORAGE_CONNECTION_STRING environment variable or configure ' .
                        'Documents.storage.azure.connectionString in app.php/app_local.php. ' .
                        'See docs/azure-blob-storage-configuration.md for setup instructions. ' .
                        'Falling back to local storage.',
                );
                $this->adapter = 'local';
                $this->initializeLocalAdapter();

                return;
            }

            try {
                $blobServiceClient = BlobServiceClient::fromConnectionString($connectionString);
                $containerClient = $blobServiceClient->getContainerClient($container);
                $adapter = new AzureBlobStorageAdapter($containerClient, $prefix);
                $this->filesystem = new FlysystemFilesystem($adapter);
                Log::info('Initialized Azure Blob Storage adapter', [
                    'container' => $container,
                    'prefix' => $prefix,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to initialize Azure Blob Storage: ' . $e->getMessage());
                $this->adapter = 'local';
                $this->initializeLocalAdapter();
            }
        } else {
            // Local filesystem adapter (default)
            $this->initializeLocalAdapter();
        }
    }

    /**
     * Initialize local filesystem adapter
     *
     * @return void
     * @throws \RuntimeException If storage directory cannot be created or is not writable
     */
    private function initializeLocalAdapter(): void
    {
        $config = Configure::read('Documents.storage', []);
        $localConfig = $config['local'] ?? [];
        $this->localBasePath = $localConfig['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

        // Ensure directory exists
        if (!is_dir($this->localBasePath)) {
            if (!mkdir($this->localBasePath, 0755, true)) {
                throw new RuntimeException(
                    sprintf('Failed to create storage directory: %s', $this->localBasePath)
                );
            }
        }

        // Verify directory is writable
        if (!is_writable($this->localBasePath)) {
            // Attempt to fix permissions
            if (!@chmod($this->localBasePath, 0755)) {
                Log::warning('Failed to set permissions on storage directory', [
                    'path' => $this->localBasePath,
                ]);
            }

            // Re-check writability after attempting to fix permissions
            if (!is_writable($this->localBasePath)) {
                throw new RuntimeException(
                    sprintf(
                        'Storage directory is not writable: %s. Please check directory permissions.',
                        $this->localBasePath
                    )
                );
            }
        }

        $adapter = new LocalFilesystemAdapter($this->localBasePath);
        $this->filesystem = new FlysystemFilesystem($adapter);
        Log::info('Initialized local filesystem adapter', [
            'path' => $this->localBasePath,
        ]);
    }

    /**
     * Get a filesystem instance for a specific storage adapter
     *
     * This method creates a Flysystem instance configured for the specified adapter type.
     * Used when retrieving files that may have been stored with a different adapter
     * than the currently configured one.
     *
     * @param string $adapterType The storage adapter type ('local' or 'azure')
     * @return \League\Flysystem\Filesystem|null Filesystem instance or null on error
     */
    private function getFilesystemForAdapter(string $adapterType): ?FlysystemFilesystem
    {
        if ($adapterType === 'azure') {
            $config = Configure::read('Documents.storage', []);
            $azureConfig = $config['azure'] ?? [];
            $connectionString = $azureConfig['connectionString'] ?? null;
            $container = $azureConfig['container'] ?? 'documents';
            $prefix = $azureConfig['prefix'] ?? '';

            if (empty($connectionString)) {
                Log::error('Azure storage connection string not configured for document retrieval');
                return null;
            }

            try {
                $blobServiceClient = BlobServiceClient::fromConnectionString($connectionString);
                $containerClient = $blobServiceClient->getContainerClient($container);
                $adapter = new AzureBlobStorageAdapter($containerClient, $prefix);
                return new FlysystemFilesystem($adapter);
            } catch (Exception $e) {
                Log::error('Failed to initialize Azure filesystem for retrieval: ' . $e->getMessage());
                return null;
            }
        } elseif ($adapterType === 'local') {
            $config = Configure::read('Documents.storage', []);
            $localConfig = $config['local'] ?? [];
            $basePath = $localConfig['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

            if (!is_dir($basePath)) {
                Log::error('Local storage path does not exist: ' . $basePath);
                return null;
            }

            $adapter = new LocalFilesystemAdapter($basePath);
            return new FlysystemFilesystem($adapter);
        }

        Log::error('Unknown storage adapter type: ' . $adapterType);
        return null;
    }

    /**
     * Sanitize file path to prevent directory traversal attacks
     *
     * @param string $path The path to sanitize
     * @return string Sanitized path
     */
    private function sanitizePath(string $path): string
    {
        // Remove any directory traversal attempts
        $path = str_replace(['../', '..\\', '\\'], '', $path);

        // Remove leading slashes
        $path = ltrim($path, '/\\');

        // Normalize path separators to forward slashes
        $path = str_replace('\\', '/', $path);

        return $path;
    }

    /**
     * Create a document from an uploaded file
     *
     * This method handles the complete document creation workflow:
     * 1. Validates the uploaded file
     * 2. Generates a unique storage filename
     * 3. Stores the physical file
     * 4. Creates the document database record
     * 5. Returns the document ID
     *
     * @param \Laminas\Diactoros\UploadedFile $file The uploaded file object
     * @param string $entityType The entity type (e.g., 'Waivers.WaiverTypes')
     * @param int $entityId The entity ID this document belongs to
     * @param int $uploadedBy The member ID who uploaded the file
     * @param array $metadata Optional metadata to store with the document
     * @param string $subDirectory Optional subdirectory within storage base (e.g., 'waiver-templates')
     * @param array $allowedExtensions Optional array of allowed file extensions (default: ['pdf'])
     * @return \App\Services\ServiceResult Success with document ID, or failure with error message
     */
    public function createDocument(
        UploadedFile $file,
        string $entityType,
        int $entityId,
        int $uploadedBy,
        array $metadata = [],
        string $subDirectory = '',
        array $allowedExtensions = ['pdf'],
    ): ServiceResult {
        // Validate file upload
        if ($file->getSize() === 0 || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->handleUploadError($file->getError());
        }

        // Get original filename and extension
        $originalName = $file->getClientFilename();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Validate file extension
        if (!in_array($extension, $allowedExtensions)) {
            $allowed = implode(', ', $allowedExtensions);

            return new ServiceResult(
                false,
                __('Invalid file type. Allowed types: {0}', $allowed),
            );
        }

        // Generate storage path
        $storedFilename = $this->generateUniqueFilename($extension);
        $relativePath = $this->sanitizePath($subDirectory ? $subDirectory . '/' . $storedFilename : $storedFilename);

        // Get file stream for efficient processing
        try {
            $fileStream = $file->getStream();
        } catch (Exception $e) {
            Log::error('Failed to read uploaded file stream');

            return new ServiceResult(
                false,
                __('Failed to read uploaded file'),
            );
        }

        // Validate file size before loading into memory to prevent memory exhaustion
        $fileSize = null;

        // Try to get size from stream first
        try {
            $fileSize = $fileStream->getSize();
        } catch (Exception $e) {
            // Fall back to uploaded file info if stream size fails
            $fileSize = $file->getSize();
        }

        // If we still don't have a size, try metadata/fstat
        if ($fileSize === null) {
            try {
                $resource = $fileStream->detach();
                if (is_resource($resource)) {
                    $stats = fstat($resource);
                    $fileSize = $stats['size'] ?? null;
                    // Reattach the resource to the stream
                    $fileStream = new \Laminas\Diactoros\Stream($resource);
                }
            } catch (Exception $e) {
                Log::warning('Could not determine file size for validation', [
                    'filename' => $originalName,
                ]);
            }
        }

        // Get maximum file size from configuration (default: 50 MB)
        $maxFileSize = Configure::read('Documents.maxFileSize', 50 * 1024 * 1024);

        // Check against maximum allowed size
        if ($fileSize !== null && $fileSize > $maxFileSize) {
            $maxSizeMB = round($maxFileSize / (1024 * 1024), 2);
            $fileSizeMB = round($fileSize / (1024 * 1024), 2);

            Log::warning('File size exceeds maximum allowed size', [
                'filename' => $originalName,
                'file_size_mb' => $fileSizeMB,
                'max_size_mb' => $maxSizeMB,
            ]);

            return new ServiceResult(
                false,
                __('File size ({0} MB) exceeds maximum allowed size of {1} MB', $fileSizeMB, $maxSizeMB),
            );
        }

        // Read contents for checksum calculation and storage
        // Note: File size has been validated to be within acceptable limits.
        // The entire file is loaded into memory for checksum calculation and storage.
        // PHP memory limit and Azure SDK buffer the content anyway, so this approach
        // is optimal for the expected use case.
        try {
            $fileStream->rewind();
            $fileContents = $fileStream->getContents();
        } catch (Exception $e) {
            Log::error('Failed to read file contents');

            return new ServiceResult(
                false,
                __('Failed to read file contents'),
            );
        }

        // Calculate file checksum
        $checksum = hash('sha256', $fileContents);

        // Store file using Flysystem
        try {
            $this->filesystem->write($relativePath, $fileContents);
        } catch (Exception $e) {
            Log::error('Failed to store file');

            return new ServiceResult(
                false,
                __('Failed to store file: {0}', $e->getMessage()),
            );
        }

        // Create document record
        $document = $this->Documents->newEntity([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'uploaded_by' => $uploadedBy,
            'original_filename' => $originalName,
            'stored_filename' => $storedFilename,
            'file_path' => $relativePath,
            'mime_type' => $file->getClientMediaType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'checksum' => $checksum,
            'storage_adapter' => $this->adapter,
            'metadata' => json_encode(array_merge(
                ['source' => 'web_upload'],
                $metadata,
            )),
        ]);

        if (!$this->Documents->save($document)) {
            // Delete the uploaded file if document save failed
            try {
                $this->filesystem->delete($relativePath);
            } catch (Exception $e) {
                Log::warning('Failed to delete file after document save failure: ' . $e->getMessage());
            }
            Log::error('Failed to save document record', [
                'errors' => $document->getErrors(),
            ]);

            return new ServiceResult(
                false,
                __('Failed to save document record.'),
            );
        }

        Log::info('Document created successfully', [
            'document_id' => $document->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'filename' => $originalName,
        ]);

        return new ServiceResult(true, null, $document->id);
    }

    /**
     * Get a download response for a document
     *
     * This method handles file retrieval and response generation for downloading documents.
     * It supports both local filesystem and remote storage (Azure Blob Storage, etc) via Flysystem.
     * The method uses the storage_adapter field from the document record to determine which
     * storage backend to use, allowing documents stored with different adapters to coexist.
     *
     * @param \App\Model\Entity\Document $document The document entity
     * @param string|null $downloadName Optional custom filename for download
     * @return \Cake\Http\Response|null Response with file, or null on error
     */
    public function getDocumentDownloadResponse(
        Document $document,
        ?string $downloadName = null,
    ): ?Response {
        // Get the filesystem instance for the adapter that was used to store this document
        $documentAdapter = $document->storage_adapter ?? 'local';
        $filesystem = $this->getFilesystemForAdapter($documentAdapter);

        if ($filesystem === null) {
            Log::error('Failed to initialize filesystem for document retrieval', [
                'document_id' => $document->id,
                'storage_adapter' => $documentAdapter,
            ]);
            return null;
        }

        // Check if file exists
        try {
            if (!$filesystem->fileExists($document->file_path)) {
                Log::error('Document file not found', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                    'adapter' => $documentAdapter,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('Error checking file existence: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'adapter' => $documentAdapter,
            ]);

            return null;
        }

        // Use original filename if no custom name provided
        if ($downloadName === null) {
            $downloadName = $document->original_filename;
        }

        // For local adapter, we can use the direct file path for better performance
        if ($documentAdapter === 'local') {
            $config = Configure::read('Documents.storage', []);
            $localConfig = $config['local'] ?? [];
            $basePath = $localConfig['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

            // Sanitize the file path before processing
            $sanitizedPath = $this->sanitizePath($document->file_path);
            $relativePath = str_replace('/', DIRECTORY_SEPARATOR, $sanitizedPath);
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
            $resolvedPath = realpath($fullPath);

            // Security: Ensure resolved path is within the base path (prevent directory traversal)
            if (
                $resolvedPath !== false && file_exists($resolvedPath) &&
                strpos($resolvedPath, realpath($basePath)) === 0
            ) {
                $response = new Response();

                return $response->withFile(
                    $resolvedPath,
                    [
                        'download' => true,
                        'name' => $downloadName,
                    ],
                );
            }
        }

        // For remote storage (or if local file path failed), read through Flysystem
        try {
            $fileContents = $filesystem->read($document->file_path);

            $response = new Response();
            $response = $response->withStringBody($fileContents);
            $response = $response->withType($document->mime_type);
            $response = $response->withDownload($downloadName);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to read document file: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'adapter' => $documentAdapter,
            ]);

            return null;
        }
    }

    /**
     * Update the entity_id of an existing document
     *
     * This is useful when you need to save the parent entity first to get its ID,
     * then update the document's entity_id reference.
     *
     * @param int $documentId The document ID to update
     * @param int $entityId The new entity ID
     * @return \App\Services\ServiceResult Success or failure
     */
    public function updateDocumentEntityId(int $documentId, int $entityId): ServiceResult
    {
        try {
            $document = $this->Documents->get($documentId);
            $document->entity_id = $entityId;

            if ($this->Documents->save($document)) {
                return new ServiceResult(true);
            }

            return new ServiceResult(
                false,
                __('Failed to update document entity reference.'),
            );
        } catch (Exception $e) {
            Log::error('Error updating document entity_id', [
                'document_id' => $documentId,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return new ServiceResult(
                false,
                __('Error updating document reference.'),
            );
        }
    }

    /**
     * Delete a document and its associated file
     *
     * This method deletes both the physical file and the database record.
     * It uses the storage_adapter field from the document record to determine
     * which storage backend to use for file deletion.
     *
     * @param int $documentId The document ID to delete
     * @return \App\Services\ServiceResult Success or failure
     */
    public function deleteDocument(int $documentId): ServiceResult
    {
        try {
            $document = $this->Documents->get($documentId);

            // Get the filesystem instance for the adapter that was used to store this document
            $documentAdapter = $document->storage_adapter ?? 'local';
            $filesystem = $this->getFilesystemForAdapter($documentAdapter);

            // Delete physical file first using the appropriate filesystem
            if ($filesystem !== null) {
                try {
                    if ($filesystem->fileExists($document->file_path)) {
                        $filesystem->delete($document->file_path);
                        Log::info('Document file deleted', [
                            'document_id' => $documentId,
                            'file_path' => $document->file_path,
                            'adapter' => $documentAdapter,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to delete physical file', [
                        'document_id' => $documentId,
                        'file_path' => $document->file_path,
                        'adapter' => $documentAdapter,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('Could not initialize filesystem for document deletion', [
                    'document_id' => $documentId,
                    'adapter' => $documentAdapter,
                ]);
            }

            // Delete document record
            if ($this->Documents->delete($document)) {
                Log::info('Document record deleted', ['document_id' => $documentId]);

                return new ServiceResult(true);
            }

            return new ServiceResult(
                false,
                __('Failed to delete document record.'),
            );
        } catch (Exception $e) {
            Log::error('Error deleting document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return new ServiceResult(
                false,
                __('Error deleting document.'),
            );
        }
    }

    /**
     * Generate a unique filename for storage
     *
     * @param string $extension File extension
     * @return string Unique filename
     */
    private function generateUniqueFilename(string $extension): string
    {
        return uniqid('doc_', true) . '.' . $extension;
    }

    /**
     * Handle upload errors and return appropriate ServiceResult
     *
     * @param int $errorCode PHP upload error code
     * @return \App\Services\ServiceResult
     */
    private function handleUploadError(int $errorCode): ServiceResult
    {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
            UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form'),
            UPLOAD_ERR_PARTIAL => __('The uploaded file was only partially uploaded'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk'),
            UPLOAD_ERR_EXTENSION => __('A PHP extension stopped the file upload'),
        ];

        $message = $errorMessages[$errorCode] ?? __('Unknown upload error');

        return new ServiceResult(false, $message);
    }
}
