<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantContext;
use App\Services\Storage\AzureBlobClientFactory;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use Cake\Core\Configure;
use Cake\Routing\Router;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use RuntimeException;

/** Publishes explicitly public artwork at immutable URLs, served without application requests. */
class PublicAssetService
{
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /** @param \League\Flysystem\FilesystemOperator|null $filesystem Optional storage adapter */
    public function __construct(private ?FilesystemOperator $filesystem = null)
    {
    }

    /**
     * Publish normalized PNG artwork. Authorization and image normalization belong to the caller.
     *
     * @param string $contents Canonical PNG bytes
     * @return string Tenant-namespaced immutable asset key
     */
    public function publishPng(string $contents): string
    {
        if (!str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('Public artwork must be a normalized PNG image.');
        }
        $tenant = TenantContext::tryCurrent();
        if ($tenant === null && filter_var(env('KMP_TENANCY_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Publishing public assets requires tenant context.');
        }
        $namespace = substr(hash('sha256', $tenant?->id ?? 'standalone'), 0, 32);
        $key = 'images/' . $namespace . '/' . hash('sha256', $contents) . '.png';
        $filesystem = $this->filesystem ??= $this->createFilesystem();
        if (!$filesystem->fileExists($key)) {
            $options = [
                'httpHeaders' => ['contentType' => 'image/png', 'cacheControl' => self::CACHE_CONTROL],
                'mimetype' => 'image/png', 'CacheControl' => self::CACHE_CONTROL,
            ];
            if (Configure::read('PublicAssets.adapter', 'local') === 'local') {
                $options['visibility'] = Visibility::PUBLIC;
            }
            $filesystem->write($key, $contents, $options);
        }

        return $key;
    }

    /**
     * @param string $key Stored asset key
     * @return string Direct public URL; changing a file creates a different URL
     */
    public static function url(string $key): string
    {
        if (!preg_match('#^images/[a-f0-9]{32}/[a-f0-9]{64}\.png$#D', $key)) {
            throw new RuntimeException('Invalid public asset key.');
        }
        $base = (string)Configure::read('PublicAssets.baseUrl', '');
        if ($base === '') {
            if (Configure::read('PublicAssets.adapter', 'local') !== 'local') {
                throw new RuntimeException('Remote public assets require a public base URL.');
            }
            $base = Router::url('/public-assets/', true);
        }

        return rtrim($base, '/') . '/' . $key;
    }

    /** @return \League\Flysystem\FilesystemOperator */
    private function createFilesystem(): FilesystemOperator
    {
        if (Configure::read('PublicAssets.adapter', 'local') === 'azure') {
            if (!Configure::read('PublicAssets.baseUrl')) {
                throw new RuntimeException('Configure a public object-store or CDN URL for public assets.');
            }
            $config = (array)Configure::read('Documents.storage.azure', []);
            $container = (string)Configure::read('PublicAssets.azureContainer', 'public-assets');
            $client = AzureBlobClientFactory::create($config)->getContainerClient($container);

            // Container publication, CORS, and infrastructure provisioning are administrative operations.
            return new Filesystem(new AzureBlobStorageAdapter($client));
        }
        if (Configure::read('PublicAssets.adapter', 'local') !== 'local') {
            throw new RuntimeException('Unsupported public asset storage adapter.');
        }

        return new Filesystem(new LocalFilesystemAdapter(
            (string)Configure::read('PublicAssets.localPath', WWW_ROOT . 'public-assets'),
            PortableVisibilityConverter::fromArray([], Visibility::PUBLIC),
        ));
    }
}
