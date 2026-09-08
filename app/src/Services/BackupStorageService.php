<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Storage\AzureBlobClientFactory;
use Aws\S3\S3Client;
use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use Cake\Core\Configure;
use Cake\Log\Log;
use Exception;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;

/**
 * Manages backup file storage using the same adapter pattern as DocumentService.
 *
 * Stores backups in a `backups/` prefix within the configured storage backend.
 */
class BackupStorageService
{
    private FlysystemFilesystem $filesystem;
    private string $adapter;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->initializeFilesystem();
    }

    /**
     * Write a backup file to storage.
     */
    public function write(string $filename, string $data): void
    {
        try {
            $this->filesystem->write($filename, $data);
        } catch (UnableToWriteFile $e) {
            $message = "Unable to write backup '{$filename}' to {$this->adapter} storage";
            if ($e->reason()) {
                $message .= ": {$e->reason()}";
            }
            $previous = $e->getPrevious();
            if ($previous) {
                $message .= " (Underlying error: {$previous->getMessage()})";
            }
            throw new RuntimeException($message, 0, $e);
        }
    }

    /**
     * Read a backup file from storage.
     */
    public function read(string $filename): string
    {
        return $this->filesystem->read($filename);
    }

    /**
     * Stream a backup into configured storage without loading it into memory.
     *
     * @param resource $stream
     */
    public function writeStream(string $filename, mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('Backup write stream is invalid.');
        }

        try {
            $this->filesystem->writeStream($filename, $stream);
        } catch (FilesystemException $e) {
            throw new RuntimeException(
                sprintf('Unable to write backup object to %s storage.', $this->adapter),
                0,
                $e,
            );
        }
    }

    /**
     * Open a stored backup as a stream.
     *
     * @return resource
     */
    public function readStream(string $filename): mixed
    {
        try {
            $stream = $this->filesystem->readStream($filename);
        } catch (UnableToReadFile $e) {
            throw new RuntimeException(
                sprintf('Unable to read backup object from %s storage.', $this->adapter),
                0,
                $e,
            );
        }
        if (!is_resource($stream)) {
            throw new RuntimeException('Backup storage returned an invalid read stream.');
        }

        return $stream;
    }

    /**
     * Delete a backup file from storage.
     */
    public function delete(string $filename): void
    {
        $this->filesystem->delete($filename);
    }

    /**
     * Check if a backup file exists.
     */
    public function exists(string $filename): bool
    {
        return $this->filesystem->fileExists($filename);
    }

    /**
     * List all backup files.
     *
     * @return array<string> Filenames
     */
    public function listFiles(): array
    {
        $files = [];
        $listing = $this->filesystem->listContents('', false);
        foreach ($listing as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Get the active storage adapter type.
     */
    public function getAdapterType(): string
    {
        return $this->adapter;
    }

    /**
     * Initialize filesystem.
     *
     * @return void
     */
    private function initializeFilesystem(): void
    {
        $config = Configure::read('Backups.storage', []);
        $this->adapter = $config['adapter'] ?? 'local';

        if ($this->adapter === 'azure') {
            $azureConfig = $config['azure'] ?? [];
            if (!$this->azureConfigHasCredential($azureConfig)) {
                throw new RuntimeException(
                    'Azure backup storage requires a connection string or managed identity account name.',
                );
            }

            try {
                $this->filesystem = $this->createAzureFilesystem($azureConfig);
            } catch (Exception $e) {
                Log::error('Azure backup storage initialization failed.');
                throw new RuntimeException('Azure backup storage initialization failed.', 0, $e);
            }
        } elseif ($this->adapter === 's3') {
            $s3Config = $config['s3'] ?? [];
            $bucket = $s3Config['bucket'] ?? null;

            if (empty($bucket)) {
                throw new RuntimeException('S3 backup storage requires a bucket.');
            }

            try {
                $clientConfig = [
                    'version' => 'latest',
                    'region' => $s3Config['region'] ?? 'us-east-1',
                ];
                if (!empty($s3Config['key']) && !empty($s3Config['secret'])) {
                    $clientConfig['credentials'] = [
                        'key' => $s3Config['key'],
                        'secret' => $s3Config['secret'],
                    ];
                }
                if (!empty($s3Config['endpoint'])) {
                    $clientConfig['endpoint'] = $s3Config['endpoint'];
                    $clientConfig['use_path_style_endpoint'] = true;
                }

                $s3Client = new S3Client($clientConfig);
                $adapter = new AwsS3V3Adapter($s3Client, $bucket, 'backups/');
                $this->filesystem = new FlysystemFilesystem($adapter);
            } catch (Exception $e) {
                Log::error('S3 backup storage initialization failed.');
                throw new RuntimeException('S3 backup storage initialization failed.', 0, $e);
            }
        } elseif ($this->adapter === 'local') {
            $this->initializeLocalAdapter();
        } else {
            throw new RuntimeException('Unsupported backup storage adapter.');
        }
    }

    /**
     * @param array<string, mixed> $azureConfig Azure storage config
     */
    private function azureConfigHasCredential(array $azureConfig): bool
    {
        try {
            AzureBlobClientFactory::normalize($azureConfig);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Build Azure backup storage using a connection string or managed identity.
     *
     * @param array<string, mixed> $azureConfig Azure storage config
     */
    protected function createAzureFilesystem(array $azureConfig): FlysystemFilesystem
    {
        $blobServiceClient = AzureBlobClientFactory::create($azureConfig);

        $container = $azureConfig['container'] ?? null;
        if (
            !is_string($container) || !preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/D', $container)
            || str_contains($container, '--')
        ) {
            throw new RuntimeException('Azure backup storage requires an explicit valid archive container.');
        }
        // Dedicated infrastructure owns container creation; runtime access is read-only.
        $containerClient = $blobServiceClient->getContainerClient($container);
        $adapter = new AzureBlobStorageAdapter($containerClient, 'backups/');

        return new FlysystemFilesystem($adapter);
    }

    /**
     * Initialize local adapter.
     *
     * @return void
     */
    private function initializeLocalAdapter(): void
    {
        $backupDir = (string)Configure::read('Backups.local.path', ROOT . DS . 'backups');
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
                throw new RuntimeException("Cannot create backup directory: {$backupDir}");
            }
        }

        $adapter = new LocalFilesystemAdapter($backupDir);
        $this->filesystem = new FlysystemFilesystem($adapter);
    }
}
