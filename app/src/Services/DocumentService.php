<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Document;
use App\Model\Table\DocumentsTable;
use Aws\S3\S3Client;
use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobServiceClient;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;
use Throwable;

/**
 * Centralized document management service for file uploads, storage, and retrieval.
 * 
 * Uses Flysystem to abstract storage operations across local filesystem, Azure Blob Storage, and S3.
 * Configuration via 'Documents' key in config/app_local.php.
 * 
 * @see \App\Services\ServiceResult Standard service result pattern
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
     * Storage adapter type ('local', 'azure', or 's3')
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
                $containerClient->createIfNotExists();
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
        } elseif ($this->adapter === 's3') {
            // Amazon S3 configuration
            $s3Config = $config['s3'] ?? [];
            $bucket = $s3Config['bucket'] ?? null;
            $region = $s3Config['region'] ?? 'us-east-1';
            $prefix = $s3Config['prefix'] ?? '';
            $key = $s3Config['key'] ?? null;
            $secret = $s3Config['secret'] ?? null;
            $endpoint = $s3Config['endpoint'] ?? null;
            $sessionToken = $s3Config['sessionToken'] ?? null;
            $usePathStyleEndpoint = (bool)($s3Config['usePathStyleEndpoint'] ?? false);

            if (empty($bucket)) {
                Log::error(
                    'S3 bucket not configured. ' .
                        'Set AWS_S3_BUCKET environment variable or configure ' .
                        'Documents.storage.s3.bucket in app.php/app_local.php. ' .
                        'Falling back to local storage.',
                );
                $this->adapter = 'local';
                $this->initializeLocalAdapter();

                return;
            }

            if (!class_exists(S3Client::class) || !class_exists(AwsS3V3Adapter::class)) {
                Log::error(
                    'S3 storage adapter dependencies missing. ' .
                        'Install with: composer require league/flysystem-aws-s3-v3. ' .
                        'Falling back to local storage.',
                );
                $this->adapter = 'local';
                $this->initializeLocalAdapter();

                return;
            }

            try {
                $clientConfig = [
                    'version' => 'latest',
                    'region' => $region,
                    'use_path_style_endpoint' => $usePathStyleEndpoint,
                ];

                if (!empty($endpoint)) {
                    $clientConfig['endpoint'] = $endpoint;
                }

                if (!empty($key) && !empty($secret)) {
                    $clientConfig['credentials'] = [
                        'key' => $key,
                        'secret' => $secret,
                    ];

                    if (!empty($sessionToken)) {
                        $clientConfig['credentials']['token'] = $sessionToken;
                    }
                }

                $s3Client = new S3Client($clientConfig);
                $adapter = new AwsS3V3Adapter($s3Client, $bucket, $prefix);
                $this->filesystem = new FlysystemFilesystem($adapter);

                Log::info('Initialized S3 storage adapter', [
                    'bucket' => $bucket,
                    'region' => $region,
                    'prefix' => $prefix,
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to initialize S3 storage: ' . $e->getMessage());
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
     * @param string $adapterType The storage adapter type ('local', 'azure', or 's3')
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
                $containerClient->createIfNotExists();
                $adapter = new AzureBlobStorageAdapter($containerClient, $prefix);
                return new FlysystemFilesystem($adapter);
            } catch (Exception $e) {
                Log::error('Failed to initialize Azure filesystem for retrieval: ' . $e->getMessage());
                return null;
            }
        } elseif ($adapterType === 's3') {
            $config = Configure::read('Documents.storage', []);
            $s3Config = $config['s3'] ?? [];
            $bucket = $s3Config['bucket'] ?? null;
            $region = $s3Config['region'] ?? 'us-east-1';
            $prefix = $s3Config['prefix'] ?? '';
            $key = $s3Config['key'] ?? null;
            $secret = $s3Config['secret'] ?? null;
            $endpoint = $s3Config['endpoint'] ?? null;
            $sessionToken = $s3Config['sessionToken'] ?? null;
            $usePathStyleEndpoint = (bool)($s3Config['usePathStyleEndpoint'] ?? false);

            if (empty($bucket)) {
                Log::error('S3 bucket not configured for document retrieval');
                return null;
            }

            if (!class_exists(S3Client::class) || !class_exists(AwsS3V3Adapter::class)) {
                Log::error(
                    'S3 storage adapter dependencies missing for retrieval. ' .
                        'Install with: composer require league/flysystem-aws-s3-v3.',
                );
                return null;
            }

            try {
                $clientConfig = [
                    'version' => 'latest',
                    'region' => $region,
                    'use_path_style_endpoint' => $usePathStyleEndpoint,
                ];

                if (!empty($endpoint)) {
                    $clientConfig['endpoint'] = $endpoint;
                }

                if (!empty($key) && !empty($secret)) {
                    $clientConfig['credentials'] = [
                        'key' => $key,
                        'secret' => $secret,
                    ];

                    if (!empty($sessionToken)) {
                        $clientConfig['credentials']['token'] = $sessionToken;
                    }
                }

                $s3Client = new S3Client($clientConfig);
                $adapter = new AwsS3V3Adapter($s3Client, $bucket, $prefix);
                return new FlysystemFilesystem($adapter);
            } catch (Throwable $e) {
                Log::error('Failed to initialize S3 filesystem for retrieval: ' . $e->getMessage());
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
     * Determine whether a JPEG preview exists for the provided document.
     *
     * @param \App\Model\Entity\Document $document Document entity reference
     * @return bool True when preview file can be located, otherwise false
     */
    public function documentPreviewExists(Document $document): bool
    {
        $adapter = $document->storage_adapter ?? 'local';
        $filesystem = $this->getFilesystemForAdapter($adapter);

        if ($filesystem === null) {
            return false;
        }

        $previewPath = preg_replace('/\.pdf$/i', '_preview.jpg', $document->file_path ?? '');
        if (empty($previewPath)) {
            return false;
        }

        $sanitizedPreviewPath = $this->sanitizePath($previewPath);

        try {
            return $filesystem->fileExists($sanitizedPreviewPath);
        } catch (Exception $e) {
            Log::debug('Error checking preview existence', [
                'document_id' => $document->id,
                'adapter' => $adapter,
                'preview_path' => $sanitizedPreviewPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
     * @param string|null $previewTempPath Optional path to a temporary JPEG preview to store alongside the document
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
        ?string $previewTempPath = null,
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

            if ($previewTempPath !== null && file_exists($previewTempPath)) {
                @unlink($previewTempPath);
            }

            return new ServiceResult(
                false,
                __('Failed to store file: {0}', $e->getMessage()),
            );
        }
        if ($previewTempPath !== null && !file_exists($previewTempPath)) {
            $previewTempPath = null;
        }

        $previewResult = null;

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

            if ($previewTempPath !== null && file_exists($previewTempPath)) {
                @unlink($previewTempPath);
            }

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

        if ($previewTempPath !== null) {
            $previewResult = $this->savePreviewFromTemp($relativePath, $previewTempPath);

            if (!$previewResult->success) {
                $error = $previewResult->getError() ?? $previewResult->reason;

                Log::warning('PDF preview copy failed', [
                    'document_id' => $document->id,
                    'error' => $error,
                ]);
            } else {
                Log::info('PDF preview stored', [
                    'document_id' => $document->id,
                    'preview_path' => $previewResult->getData(),
                ]);
            }
        }

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
     * Get an inline response for a document (suitable for iframe/object embedding).
     *
     * @param \App\Model\Entity\Document $document The document entity
     * @param string|null $inlineName Optional custom filename for inline disposition
     * @return \Cake\Http\Response|null Response with file, or null on error
     */
    public function getDocumentInlineResponse(
        Document $document,
        ?string $inlineName = null,
    ): ?Response {
        // Get the filesystem instance for the adapter that was used to store this document
        $documentAdapter = $document->storage_adapter ?? 'local';
        $filesystem = $this->getFilesystemForAdapter($documentAdapter);

        if ($filesystem === null) {
            Log::error('Failed to initialize filesystem for inline document retrieval', [
                'document_id' => $document->id,
                'storage_adapter' => $documentAdapter,
            ]);
            return null;
        }

        // Check if file exists
        try {
            if (!$filesystem->fileExists($document->file_path)) {
                Log::error('Document file not found for inline retrieval', [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                    'adapter' => $documentAdapter,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('Error checking file existence for inline retrieval: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'adapter' => $documentAdapter,
            ]);

            return null;
        }

        // Use original filename if no custom name provided
        if ($inlineName === null) {
            $inlineName = $document->original_filename;
        }

        // Prevent header injection through filename
        $inlineName = str_replace(["\r", "\n", '"'], '', (string)$inlineName);

        // For local adapter, use the direct file path for better performance
        if ($documentAdapter === 'local') {
            $config = Configure::read('Documents.storage', []);
            $localConfig = $config['local'] ?? [];
            $basePath = $localConfig['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

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
                        'download' => false,
                        'name' => $inlineName,
                    ],
                )->withType($document->mime_type ?: 'application/octet-stream');
            }
        }

        // For remote storage (or if local file path failed), read through Flysystem
        try {
            $fileContents = $filesystem->read($document->file_path);

            $response = new Response();
            $response = $response->withStringBody($fileContents);
            $response = $response->withType($document->mime_type ?: 'application/octet-stream');

            // Explicit inline disposition helps browser PDF viewers in iframe contexts.
            $encodedFilename = rawurlencode($inlineName);
            $response = $response->withHeader(
                'Content-Disposition',
                "inline; filename=\"{$inlineName}\"; filename*=UTF-8''{$encodedFilename}"
            );

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to read document file for inline retrieval: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
                'adapter' => $documentAdapter,
            ]);

            return null;
        }
    }

    /**
     * Get an inline preview response for a document's generated JPEG preview.
     *
     * @param \App\Model\Entity\Document $document Document entity with stored file path
     * @return \Cake\Http\Response|null Response streaming the preview image, or null if unavailable
     */
    public function getDocumentPreviewResponse(Document $document): ?Response
    {
        $documentAdapter = $document->storage_adapter ?? 'local';
        $filesystem = $this->getFilesystemForAdapter($documentAdapter);

        if ($filesystem === null) {
            Log::warning('Filesystem unavailable for document preview retrieval', [
                'document_id' => $document->id,
                'adapter' => $documentAdapter,
            ]);

            return null;
        }

        $previewPath = preg_replace('/\.pdf$/i', '_preview.jpg', $document->file_path ?? '');
        if (empty($previewPath)) {
            return null;
        }

        $sanitizedPreviewPath = $this->sanitizePath($previewPath);

        try {
            if (!$filesystem->fileExists($sanitizedPreviewPath)) {
                Log::notice('Document preview not found', [
                    'document_id' => $document->id,
                    'preview_path' => $sanitizedPreviewPath,
                    'adapter' => $documentAdapter,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('Error checking preview existence', [
                'document_id' => $document->id,
                'preview_path' => $sanitizedPreviewPath,
                'adapter' => $documentAdapter,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($documentAdapter === 'local') {
            $config = Configure::read('Documents.storage', []);
            $localConfig = $config['local'] ?? [];
            $basePath = $localConfig['path'] ?? WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

            $relativePath = str_replace('/', DIRECTORY_SEPARATOR, $sanitizedPreviewPath);
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
            $resolvedPath = realpath($fullPath);

            if ($resolvedPath !== false && file_exists($resolvedPath) && strpos($resolvedPath, realpath($basePath)) === 0) {
                $response = new Response();

                return $response->withFile(
                    $resolvedPath,
                    [
                        'download' => false,
                        'name' => basename($sanitizedPreviewPath),
                    ],
                )->withType('image/jpeg');
            }
        }

        try {
            $fileContents = $filesystem->read($sanitizedPreviewPath);
            $response = new Response();
            $response = $response->withStringBody($fileContents);
            $response = $response->withType('image/jpeg');

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to read document preview', [
                'document_id' => $document->id,
                'preview_path' => $sanitizedPreviewPath,
                'adapter' => $documentAdapter,
                'error' => $e->getMessage(),
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

                    $previewPath = null;
                    if (!empty($document->file_path)) {
                        $previewPath = preg_replace('/\.pdf$/i', '_preview.jpg', $document->file_path);
                    }

                    if (!empty($previewPath)) {
                        $previewPath = $this->sanitizePath($previewPath);
                        try {
                            if ($filesystem->fileExists($previewPath)) {
                                $filesystem->delete($previewPath);
                                Log::info('Document preview deleted', [
                                    'document_id' => $documentId,
                                    'preview_path' => $previewPath,
                                    'adapter' => $documentAdapter,
                                ]);
                            }
                        } catch (Exception $e) {
                            Log::debug('Failed to delete preview image during document cleanup', [
                                'document_id' => $documentId,
                                'preview_path' => $previewPath,
                                'adapter' => $documentAdapter,
                                'error' => $e->getMessage(),
                            ]);
                        }
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
     * Persist a temporary preview image into the configured storage adapter.
     *
     * @param string $relativePdfPath Relative path of stored PDF, used to derive preview path
     * @param string $tempPreviewPath Temporary JPEG path produced during conversion
     * @return \\App\\Services\\ServiceResult Result indicating success and stored preview path
     */
    private function savePreviewFromTemp(string $relativePdfPath, string $tempPreviewPath): ServiceResult
    {
        if (!file_exists($tempPreviewPath)) {
            return new ServiceResult(false, 'Temporary preview image missing.');
        }

        $previewRelativePath = preg_replace('/\\.pdf$/i', '_preview.jpg', $relativePdfPath) ?? ($relativePdfPath . '_preview.jpg');
        $sanitizedRelativePath = $this->sanitizePath($previewRelativePath);

        try {
            if ($this->adapter === 'local') {
                $destination = $this->localBasePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sanitizedRelativePath);
                $directory = dirname($destination);

                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    return new ServiceResult(false, 'Failed to prepare directory for preview image.');
                }

                if (file_exists($destination) && !@unlink($destination)) {
                    return new ServiceResult(false, 'Failed to replace existing preview image.');
                }

                if (!@rename($tempPreviewPath, $destination)) {
                    if (!@copy($tempPreviewPath, $destination)) {
                        return new ServiceResult(false, 'Failed to copy preview into storage.');
                    }
                }

                return new ServiceResult(true, null, $sanitizedRelativePath);
            }

            $previewContents = file_get_contents($tempPreviewPath);
            if ($previewContents === false) {
                return new ServiceResult(false, 'Failed to read preview contents.');
            }

            if ($this->filesystem->fileExists($sanitizedRelativePath)) {
                $this->filesystem->delete($sanitizedRelativePath);
            }

            $this->filesystem->write($sanitizedRelativePath, $previewContents);

            return new ServiceResult(true, null, $sanitizedRelativePath);
        } catch (Exception $e) {
            return new ServiceResult(false, $e->getMessage());
        } finally {
            if (file_exists($tempPreviewPath)) {
                @unlink($tempPreviewPath);
            }
        }
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
