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
use Psr\Http\Message\UploadedFileInterface;
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
        $key = self::tenantAssetPrefix() . hash('sha256', $contents) . '.png';
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

    /** Check an immutable asset reference against the active tenant namespace. */
    public static function isTenantAssetKey(string $key): bool
    {
        $prefix = self::tenantAssetPrefix();

        return str_starts_with($key, $prefix)
            && (bool)preg_match('/^[a-f0-9]{64}\.png$/D', substr($key, strlen($prefix)));
    }

    /** Prefix for files published under the active tenant identity. */
    private static function tenantAssetPrefix(): string
    {
        $namespace = substr(hash('sha256', TenantContext::tryCurrent()?->id ?? 'standalone'), 0, 32);

        return 'images/' . $namespace . '/';
    }

    /** Decode a bounded PNG or WebP upload and strip metadata before publication. */
    public static function normalizePngUpload(
        UploadedFileInterface $upload,
        int $maxBytes,
        int $maxDimension,
    ): ?string {
        if ($upload->getError() !== UPLOAD_ERR_OK || ($upload->getSize() ?? 0) > $maxBytes) {
            return null;
        }
        $stream = $upload->getStream();
        $stream->rewind();
        $bytes = $stream->read($maxBytes + 1);
        if ($bytes === '' || strlen($bytes) > $maxBytes) {
            return null;
        }

        // Invalid raster data should produce a field error instead of decoder warnings.
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $size = @getimagesizefromstring($bytes);
        if (
            !$size || !in_array($size['mime'], ['image/png', 'image/webp'], true)
            || $size[0] > $maxDimension || $size[1] > $maxDimension
        ) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        // phpcs:enable Generic.PHP.NoSilencedErrors.Discouraged
        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);
        $output = fopen('php://temp', 'w+b');
        try {
            if (!imagepng($image, $output) || !rewind($output)) {
                return null;
            }

            return stream_get_contents($output) ?: null;
        } finally {
            fclose($output);
        }
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
