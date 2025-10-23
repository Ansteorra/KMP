<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Document;
use App\Model\Table\DocumentsTable;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Laminas\Diactoros\UploadedFile;

/**
 * Document Service
 *
 * Centralized service for document management including file uploads, storage, and retrieval.
 * This service encapsulates all document-related operations to ensure consistency across the
 * application and provide a single point of change for storage strategy modifications.
 *
 * ## Key Responsibilities
 *
 * - **File Upload Processing**: Validates and processes uploaded files
 * - **Document Creation**: Creates document records with metadata
 * - **File Storage**: Handles physical file storage (local/S3/etc)
 * - **File Retrieval**: Provides file download responses
 * - **Storage Abstraction**: Isolates storage implementation from consumers
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
 * Currently uses local filesystem storage in `/images/uploaded/`.
 * Can be extended to support S3, Azure Blob Storage, etc. by modifying
 * the storage adapter logic in this service.
 *
 * @see \App\Model\Table\DocumentsTable
 * @see \App\Model\Entity\Document
 */
class DocumentService
{
    use LocatorAwareTrait;

    /**
     * Base storage path for uploaded documents
     */
    private const STORAGE_BASE_PATH = 'images/uploaded/';

    /**
     * Documents table instance
     *
     * @var \App\Model\Table\DocumentsTable
     */
    private DocumentsTable $Documents;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->Documents = $this->fetchTable('Documents');
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
        $fullStoragePath = WWW_ROOT . '../' . self::STORAGE_BASE_PATH;

        if ($subDirectory) {
            $fullStoragePath .= $subDirectory . '/';
        }

        // Create storage directory if it doesn't exist
        if (!is_dir($fullStoragePath)) {
            if (!mkdir($fullStoragePath, 0755, true)) {
                Log::error('Failed to create storage directory: ' . $fullStoragePath);
                return new ServiceResult(
                    false,
                    __('Failed to create storage directory.')
                );
            }
        }

        $fullFilePath = $fullStoragePath . $storedFilename;

        // Move uploaded file to storage
        try {
            $file->moveTo($fullFilePath);
        } catch (\Exception $e) {
            Log::error('Failed to move uploaded file: ' . $e->getMessage());
            return new ServiceResult(
                false,
                __('Failed to upload file: {0}', $e->getMessage())
            );
        }

        // Calculate file checksum
        $checksum = hash_file('sha256', $fullFilePath);

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
            'storage_adapter' => 'local',
            'metadata' => json_encode(array_merge(
                ['source' => 'web_upload'],
                $metadata
            ))
        ]);

        if (!$this->Documents->save($document)) {
            // Delete the uploaded file if document save failed
            if (file_exists($fullFilePath)) {
                unlink($fullFilePath);
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
     * It supports both local filesystem and can be extended for remote storage (S3, etc).
     *
     * @param \App\Model\Entity\Document $document The document entity
     * @param string|null $downloadName Optional custom filename for download
     * @return \Cake\Http\Response|null Response with file, or null on error
     */
    public function getDocumentDownloadResponse(
        Document $document,
        ?string $downloadName = null
    ): ?Response {
        // Construct full file path
        $fullPath = WWW_ROOT . '../' . self::STORAGE_BASE_PATH . $document->file_path;

        // Resolve to absolute path to avoid security issues with '..'
        $resolvedPath = realpath($fullPath);

        // Check if file exists
        if ($resolvedPath === false || !file_exists($resolvedPath)) {
            Log::error('Document file not found', [
                'document_id' => $document->id,
                'expected_path' => $fullPath,
                'resolved_path' => $resolvedPath
            ]);
            return null;
        }

        // Use original filename if no custom name provided
        if ($downloadName === null) {
            $downloadName = $document->original_filename;
        }

        // Create response with file
        $response = new Response();
        return $response->withFile(
            $resolvedPath,
            [
                'download' => true,
                'name' => $downloadName
            ]
        );
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

            // Delete physical file first
            $fullPath = WWW_ROOT . '../' . self::STORAGE_BASE_PATH . $document->file_path;
            $resolvedPath = realpath($fullPath);

            if ($resolvedPath && file_exists($resolvedPath)) {
                if (!unlink($resolvedPath)) {
                    Log::warning('Failed to delete physical file', [
                        'document_id' => $documentId,
                        'path' => $resolvedPath
                    ]);
                }
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
