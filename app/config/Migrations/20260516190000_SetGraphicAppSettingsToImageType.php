<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Migrations\BaseMigration;

class SetGraphicAppSettingsToImageType extends BaseMigration
{
    private const GRAPHIC_SETTINGS = [
        'KMP.BannerLogo' => [
            'filename' => 'badge.png',
            'mime' => 'image/png',
        ],
        'KMP.Login.Graphic' => [
            'filename' => 'populace_badge.png',
            'mime' => 'image/png',
        ],
        'Member.ViewCard.Graphic' => [
            'filename' => 'auth_card_back.gif',
            'mime' => 'image/gif',
        ],
    ];

    /**
     * Mark existing graphic settings as image settings and seed source images.
     *
     * @return void
     */
    public function up(): void
    {
        foreach (self::GRAPHIC_SETTINGS as $name => $asset) {
            $payload = $this->assetPayload($asset['filename'], $asset['mime']);
            if ($payload === null) {
                $this->execute(sprintf(
                    "UPDATE app_settings SET type = 'image' " .
                    "WHERE name = '%s' AND (type IS NULL OR type = '' OR type = 'string')",
                    $this->sqlEscape($name),
                ));
                continue;
            }

            $this->execute(sprintf(
                "UPDATE app_settings SET type = 'image', value = '%s' WHERE name = '%s'",
                $this->sqlEscape($payload),
                $this->sqlEscape($name),
            ));
        }

        Cache::clear('default');
    }

    /**
     * Build a database-backed asset payload from a checked-in source image.
     */
    private function assetPayload(string $filename, string $mimeType): ?string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'img'
            . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return json_encode([
            'storage' => 'database',
            'filename' => $filename,
            'mime' => $mimeType,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'data' => base64_encode($contents),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Escape SQL string values for update statements.
     */
    private function sqlEscape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
