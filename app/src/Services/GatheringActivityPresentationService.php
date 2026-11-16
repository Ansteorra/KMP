<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\GatheringActivity;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Laminas\Diactoros\UploadedFile;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/** Validates activity appearance and publishes bounded raster icons through the public asset store. */
class GatheringActivityPresentationService
{
    use LocatorAwareTrait;

    public const MAX_ICON_BYTES = 524288;

    /** @param \App\Services\PublicAssetService|null $assets Public artwork storage */
    public function __construct(private ?PublicAssetService $assets = null)
    {
    }

    /** @return array<string> */
    public static function iconNames(): array
    {
        return IconCatalog::ids();
    }

    /**
     * @param \App\Model\Entity\GatheringActivity $activity Activity
     * @return array<string, mixed>
     */
    public static function appearanceData(GatheringActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'activity_type' => $activity->activity_type ?? 'General',
            'display_order' => $activity->display_order ?? 100,
            'icon_name' => IconCatalog::canonical($activity->icon_name) ?? $activity->icon_name ?? 'activity',
            'icon_url' => $activity->icon_source === 'upload' && $activity->icon_asset_key
                ? PublicAssetService::url($activity->icon_asset_key) : null,
            'foreground_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $activity->foreground_color ?? '')
                ? $activity->foreground_color : '#212529',
            'background_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $activity->background_color ?? '')
                ? $activity->background_color : '#e9ecef',
        ];
    }

    /**
     * @param string $foreground Foreground hex color
     * @param string $background Background hex color
     * @return float
     */
    public static function contrastRatio(string $foreground, string $background): float
    {
        $luminance = static function (string $color): ?float {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                return null;
            }
            $channels = array_map(static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            }, str_split(substr($color, 1), 2));

            return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        };
        $first = $luminance($foreground);
        $second = $luminance($background);
        if ($first === null || $second === null) {
            return 0;
        }

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * Save appearance and an optional immutable public icon.
     *
     * @param \App\Model\Entity\GatheringActivity $activity Validated activity
     * @param \Psr\Http\Message\UploadedFileInterface|null $file Optional uploaded icon
     * @param bool $removeIcon Remove the stored upload
     * @return \App\Services\ServiceResult
     */
    public function save(
        GatheringActivity $activity,
        ?UploadedFileInterface $file,
        bool $removeIcon,
    ): ServiceResult {
        if ($activity->hasErrors()) {
            return new ServiceResult(false, __('Check the activity settings.'));
        }
        $normalized = null;
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $normalized = $this->normalizeIcon($file);
            if ($normalized === null) {
                $activity->setError('icon_file', __('Upload a PNG or WebP image up to 128 × 128 pixels and 512 KB.'));

                return new ServiceResult(false, __('The activity icon is invalid.'));
            }
        }
        if ($activity->icon_source === 'upload' && !$activity->icon_asset_key && !$normalized && !$removeIcon) {
            $activity->setError('icon_file', __('Upload an icon, or choose a catalog icon.'));

            return new ServiceResult(false, __('An uploaded icon is required.'));
        }

        $table = $this->fetchTable('GatheringActivities');
        if ($removeIcon) {
            $activity->set('icon_asset_key', null);
            $activity->set('icon_source', 'catalog');
        }
        if ($normalized !== null) {
            $stream = $normalized->getStream();
            $stream->rewind();
            try {
                $key = ($this->assets ??= new PublicAssetService())->publishPng($stream->getContents());
            } catch (FilesystemException | RuntimeException $error) {
                Log::error('Activity icon publication failed: ' . $error::class);
                $activity->setError('icon_file', __('The icon could not be published. Please try again.'));

                return new ServiceResult(false, __('The activity icon could not be published.'));
            }
            $activity->set('icon_asset_key', $key);
            $activity->set('icon_source', 'upload');
        }

        // Immutable assets remain available to cached pages; unused keys can be collected separately.
        return new ServiceResult((bool)$table->save($activity));
    }

    /**
     * Decode and re-encode small raster uploads, preserving their alpha channel and stripping metadata.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $file Upload
     * @return \Psr\Http\Message\UploadedFileInterface|null
     */
    private function normalizeIcon(UploadedFileInterface $file): ?UploadedFileInterface
    {
        if ($file->getError() !== UPLOAD_ERR_OK || ($file->getSize() ?? 0) > self::MAX_ICON_BYTES) {
            return null;
        }
        $stream = $file->getStream();
        $stream->rewind();
        $bytes = $stream->read(self::MAX_ICON_BYTES + 1);
        if ($bytes === '' || strlen($bytes) > self::MAX_ICON_BYTES) {
            return null;
        }
        // Malformed raster files must fail validation without leaking decoder warnings into the response.
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $size = @getimagesizefromstring($bytes);
        if (
            !$size || !in_array($size['mime'], ['image/png', 'image/webp'], true)
            || $size[0] > 128 || $size[1] > 128
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
        imagepng($image, $output);
        rewind($output);

        return new UploadedFile($output, null, UPLOAD_ERR_OK, 'activity-icon.png', 'image/png');
    }
}
