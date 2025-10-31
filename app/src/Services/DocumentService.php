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
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

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
            $connectionString = $config['connectionString'] ?? null;
            $container = $config['container'] ?? 'documents';
            $prefix = $config['prefix'] ?? '';

            if (empty($connectionString)) {
                Log::error('Azure storage connection string not configured, falling back to local storage');
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
                    'prefix' => $prefix
                ]);
            } catch (\Exception $e) {
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
     */
    private function initializeLocalAdapter(): void
    {
        $config = Configure::read('Documents.storage', []);
        $this->localBasePath = $config['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

        // Ensure directory exists
        if (!is_dir($this->localBasePath)) {
            if (!mkdir($this->localBasePath, 0755, true)) {
                throw new \RuntimeException('Failed to create storage directory: ' . $this->localBasePath);
            }
        }

        $adapter = new LocalFilesystemAdapter($this->localBasePath);
        $this->filesystem = new FlysystemFilesystem($adapter);
        Log::info('Initialized local filesystem adapter', ['path' => $this->localBasePath]);
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
        array $allowedExtensions = ['pdf']
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
                __('Invalid file type. Allowed types: {0}', $allowed)
            );
        }

        // Generate storage path
        $storedFilename = $this->generateUniqueFilename($extension);
        $relativePath = $subDirectory ? $subDirectory . '/' . $storedFilename : $storedFilename;

        // Read file contents
        try {
            $fileStream = $file->getStream();
            $fileContents = $fileStream->getContents();
        } catch (\Exception $e) {
            Log::error('Failed to read uploaded file: ' . $e->getMessage());
            return new ServiceResult(
                false,
                __('Failed to read uploaded file: {0}', $e->getMessage())
            );
        }

        // Calculate file checksum before writing
        $checksum = hash('sha256', $fileContents);

        // Store file using Flysystem
        try {
            $this->filesystem->write($relativePath, $fileContents);
        } catch (\Exception $e) {
            Log::error('Failed to store file: ' . $e->getMessage());
            return new ServiceResult(
                false,
                __('Failed to store file: {0}', $e->getMessage())
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
                $metadata
            ))
        ]);

        if (!$this->Documents->save($document)) {
            // Delete the uploaded file if document save failed
            try {
                $this->filesystem->delete($relativePath);
            } catch (\Exception $e) {
                Log::warning('Failed to delete file after document save failure: ' . $e->getMessage());
            }
            Log::error('Failed to save document record', [
                'errors' => $document->getErrors()
            ]);
            return new ServiceResult(
                false,
                __('Failed to save document record.')
            );
        }

        Log::info('Document created successfully', [
            'document_id' => $document->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'filename' => $originalName
        ]);

        return new ServiceResult(true, null, $document->id);
    }

    /**
     * Get a download response for a document
     *
     * This method handles file retrieval and response generation for downloading documents.
     * It supports both local filesystem and remote storage (Azure Blob Storage, etc) via Flysystem.
     *
     * @param \App\Model\Entity\Document $document The document entity
     * @param string|null $downloadName Optional custom filename for download
     * @return \Cake\Http\Response|null Response with file, or null on error
     */
    public function getDocumentDownloadResponse(
        Document $document,
        ?string $downloadName = null
    ): ?Response {
        // Check if file exists
        try {
            if (!$this->filesystem->fileExists($document->file_path)) {
                Log::error('Document file not found', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                    'adapter' => $this->adapter
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error checking file existence: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path
            ]);
            return null;
        }

        // Use original filename if no custom name provided
        if ($downloadName === null) {
            $downloadName = $document->original_filename;
        }

        // For local adapter, we can use the direct file path for better performance
        if ($this->adapter === 'local' && $this->localBasePath !== null) {
            $fullPath = $this->localBasePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $document->file_path);
            $resolvedPath = realpath($fullPath);

            if ($resolvedPath !== false && file_exists($resolvedPath)) {
                $response = new Response();
                return $response->withFile(
                    $resolvedPath,
                    [
                        'download' => true,
                        'name' => $downloadName
                    ]
                );
            }
        }

        // For remote storage (or if local file path failed), read through Flysystem
        try {
            $fileContents = $this->filesystem->read($document->file_path);
            
            $response = new Response();
            $response = $response->withStringBody($fileContents);
            $response = $response->withType($document->mime_type);
            $response = $response->withDownload($downloadName);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to read document file: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path
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
                __('Failed to update document entity reference.')
            );
        } catch (\Exception $e) {
            Log::error('Error updating document entity_id', [
                'document_id' => $documentId,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            return new ServiceResult(
                false,
                __('Error updating document reference.')
            );
        }
    }

    /**
     * Delete a document and its associated file
     *
     * @param int $documentId The document ID to delete
     * @return \App\Services\ServiceResult Success or failure
     */
    public function deleteDocument(int $documentId): ServiceResult
    {
        try {
            $document = $this->Documents->get($documentId);

            // Delete physical file first using Flysystem
            try {
                if ($this->filesystem->fileExists($document->file_path)) {
                    $this->filesystem->delete($document->file_path);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete physical file', [
                    'document_id' => $documentId,
                    'file_path' => $document->file_path,
                    'error' => $e->getMessage()
                ]);
            }

            // Delete document record
            if ($this->Documents->delete($document)) {
                Log::info('Document deleted', ['document_id' => $documentId]);
                return new ServiceResult(true);
            }

            return new ServiceResult(
                false,
                __('Failed to delete document record.')
            );
        } catch (\Exception $e) {
            Log::error('Error deleting document', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return new ServiceResult(
                false,
                __('Error deleting document.')
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
