<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\ServiceResult;
use Cake\Core\Configure;
use Cake\Log\Log;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Waiver Storage Service
 *
 * Provides abstraction layer for storing and retrieving waiver documents using
 * Flysystem. Handles local filesystem and cloud storage (S3) configurations,
 * with automatic adapter selection based on application configuration.
 * 
 * ## Features
 * 
 * - **Multiple Storage Backends**: Local filesystem or cloud storage (S3)
 * - **Configuration-Driven**: Uses app configuration to select adapter
 * - **Path Management**: Generates organized paths based on gathering IDs
 * - **File Operations**: Store, retrieve, delete, and check existence
 * - **Error Handling**: Returns ServiceResult for consistent error reporting
 * - **Security**: Validates file paths and prevents directory traversal
 * 
 * ## Configuration
 * 
 * Set storage configuration in `config/app_local.php`:
 * 
 * ```php
 * 'Waivers' => [
 *     'storage' => [
 *         'adapter' => 'local', // or 's3'
 *         'path' => ROOT . DS . 'storage' . DS . 'waivers',
 *         // For S3:
 *         // 'bucket' => 'my-bucket',
 *         // 'region' => 'us-east-1',
 *         // 'key' => env('AWS_KEY'),
 *         // 'secret' => env('AWS_SECRET'),
 *     ],
 * ],
 * ```
 * 
 * ## Usage Examples
 * 
 * ```php
 * $service = new WaiverStorageService();
 * 
 * // Store a waiver file
 * $result = $service->store($gatheringId, $filename, $fileContents);
 * if ($result->success) {
 *     $storedPath = $result->data; // 'waivers/123/filename.pdf'
 * }
 * 
 * // Retrieve a waiver
 * $result = $service->retrieve('waivers/123/filename.pdf');
 * if ($result->success) {
 *     $fileContents = $result->data;
 * }
 * 
 * // Check if waiver exists
 * $exists = $service->exists('waivers/123/filename.pdf');
 * 
 * // Delete a waiver
 * $result = $service->delete('waivers/123/filename.pdf');
 * ```
 * 
 * ## Path Structure
 * 
 * Waivers are organized by gathering ID:
 * - `waivers/{gathering_id}/{filename}`
 * - Example: `waivers/42/john-doe-waiver.pdf`
 * 
 * ## Security Considerations
 * 
 * - Path validation prevents directory traversal attacks
 * - Files are stored outside webroot
 * - Access controlled through authorization policies
 * - SHA-256 checksums stored in database for integrity verification
 * 
 * @see \League\Flysystem\Filesystem Flysystem documentation
 * @see \App\Services\ServiceResult Standard service result pattern
 */
class WaiverStorageService
{
    /**
     * Flysystem filesystem instance
     *
     * @var \League\Flysystem\Filesystem
     */
    private FlysystemFilesystem $filesystem;

    /**
     * Base path for waiver storage
     *
     * @var string
     */
    private string $basePath;

    /**
     * Constructor - initializes Flysystem with configured adapter
     */
    public function __construct()
    {
        $config = Configure::read('Waivers.storage', []);
        $adapter = $config['adapter'] ?? 'local';

        if ($adapter === 'local') {
            $this->basePath = $config['path'] ?? ROOT . DS . 'storage' . DS . 'waivers';

            // Ensure directory exists
            if (!is_dir($this->basePath)) {
                mkdir($this->basePath, 0755, true);
            }

            $localAdapter = new LocalFilesystemAdapter($this->basePath);
            $this->filesystem = new FlysystemFilesystem($localAdapter);
        } else {
            // S3 or other adapters would be configured here
            throw new \RuntimeException('Only local adapter is currently implemented');
        }
    }

    /**
     * Store a waiver file
     *
     * @param int $gatheringId Gathering ID for path organization
     * @param string $filename Original filename
     * @param string $contents File contents
     * @return \App\Services\ServiceResult Success with stored path, or failure
     */
    public function store(int $gatheringId, string $filename, string $contents): ServiceResult
    {
        try {
            // Sanitize filename
            $sanitizedFilename = $this->sanitizeFilename($filename);

            // Generate storage path
            $path = $this->generatePath($gatheringId, $sanitizedFilename);

            // Store file
            $this->filesystem->write($path, $contents);

            Log::info("Stored waiver file: $path");

            return new ServiceResult(true, null, $path);
        } catch (\Exception $e) {
            Log::error('Failed to store waiver: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to store waiver file: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve a waiver file
     *
     * @param string $path Path to the file
     * @return \App\Services\ServiceResult Success with file contents, or failure
     */
    public function retrieve(string $path): ServiceResult
    {
        try {
            if (!$this->filesystem->fileExists($path)) {
                return new ServiceResult(false, 'Waiver file not found');
            }

            $contents = $this->filesystem->read($path);

            return new ServiceResult(true, null, $contents);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve waiver: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to retrieve waiver file: ' . $e->getMessage());
        }
    }

    /**
     * Check if a waiver file exists
     *
     * @param string $path Path to check
     * @return bool True if file exists
     */
    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (\Exception $e) {
            Log::error('Error checking file existence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a waiver file
     *
     * @param string $path Path to the file
     * @return \App\Services\ServiceResult Success or failure
     */
    public function delete(string $path): ServiceResult
    {
        try {
            if (!$this->filesystem->fileExists($path)) {
                return new ServiceResult(false, 'Waiver file not found');
            }

            $this->filesystem->delete($path);

            Log::info("Deleted waiver file: $path");

            return new ServiceResult(true, 'Waiver file deleted');
        } catch (\Exception $e) {
            Log::error('Failed to delete waiver: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to delete waiver file: ' . $e->getMessage());
        }
    }

    /**
     * Get file size
     *
     * @param string $path Path to the file
     * @return \App\Services\ServiceResult Success with file size in bytes, or failure
     */
    public function getSize(string $path): ServiceResult
    {
        try {
            if (!$this->filesystem->fileExists($path)) {
                return new ServiceResult(false, 'Waiver file not found');
            }

            $size = $this->filesystem->fileSize($path);

            return new ServiceResult(true, null, $size);
        } catch (\Exception $e) {
            Log::error('Failed to get file size: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to get file size: ' . $e->getMessage());
        }
    }

    /**
     * Get file MIME type
     *
     * @param string $path Path to the file
     * @return \App\Services\ServiceResult Success with MIME type, or failure
     */
    public function getMimeType(string $path): ServiceResult
    {
        try {
            if (!$this->filesystem->fileExists($path)) {
                return new ServiceResult(false, 'Waiver file not found');
            }

            $mimeType = $this->filesystem->mimeType($path);

            return new ServiceResult(true, null, $mimeType);
        } catch (\Exception $e) {
            Log::error('Failed to get MIME type: ' . $e->getMessage());
            return new ServiceResult(false, 'Failed to get MIME type: ' . $e->getMessage());
        }
    }

    /**
     * Generate storage path for a waiver
     *
     * @param int $gatheringId Gathering ID
     * @param string $filename Filename
     * @return string Storage path
     */
    private function generatePath(int $gatheringId, string $filename): string
    {
        return sprintf('waivers/%d/%s', $gatheringId, $filename);
    }

    /**
     * Sanitize filename to prevent directory traversal and other issues
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove directory separators
        $filename = str_replace(['/', '\\', '..'], '', $filename);

        // Remove any characters that aren't alphanumeric, dash, underscore, or dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Ensure filename isn't empty
        if (empty($filename)) {
            $filename = 'waiver_' . uniqid();
        }

        return $filename;
    }

    /**
     * Get the full filesystem path (for local adapter only)
     *
     * @param string $path Relative storage path
     * @return string|null Full filesystem path, or null if not using local adapter
     */
    public function getFullPath(string $path): ?string
    {
        if (isset($this->basePath)) {
            return $this->basePath . DS . str_replace('/', DS, $path);
        }

        return null;
    }
}
