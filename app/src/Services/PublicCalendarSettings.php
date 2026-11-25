<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Table\AppSettingsTable;
use Cake\Log\Log;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/** Tenant-owned content, appearance, and framing settings for /events. */
final class PublicCalendarSettings
{
    public const KEY = 'Plugin.PublicGatherings.CalendarSettings';
    public const FRAME_ORIGINS_HEADER = 'X-KMP-Calendar-Frame-Origins';

    private const PALETTES = [
        'classic' => [
            'background' => '#faf8f3', 'panel' => '#ffffff', 'text' => '#272c27', 'accent' => '#766018',
            'muted' => '#596057', 'tint' => '#f3eddb', 'rule' => '#deded3',
        ],
        'neutral' => [
            'background' => '#f4f6f8', 'panel' => '#ffffff', 'text' => '#202935', 'accent' => '#254d77',
            'muted' => '#4c5b6c', 'tint' => '#e5edf5', 'rule' => '#cbd4de',
        ],
        'dark' => [
            'background' => '#161e28', 'panel' => '#202b38', 'text' => '#f2f5f8', 'accent' => '#9dd0ff',
            'muted' => '#becddd', 'tint' => '#293848', 'rule' => '#465769',
        ],
    ];

    private const FONTS = [
        'inter' => 'Inter, system-ui, sans-serif',
        'system' => 'system-ui, sans-serif',
        'arial' => 'Arial, sans-serif',
        'verdana' => 'Verdana, sans-serif',
        'georgia' => 'Georgia, serif',
        'playfair' => '"Playfair Display", Georgia, serif',
    ];

    private const COLOR_FIELDS = ['background', 'panel', 'text', 'accent'];

    private const CHECKBOXES = [
        'show_header', 'show_subscribe', 'show_footer', 'embed_header', 'embed_intro', 'embed_footer',
    ];

    /** Use the resolved tenant’s settings table and public asset store. */
    public function __construct(
        private readonly AppSettingsTable $settings,
        private readonly PublicAssetService $assets = new PublicAssetService(),
    ) {
    }

    /** @return array<string, string> */
    public static function themeOptions(): array
    {
        return ['classic' => __('Classic'), 'neutral' => __('Neutral'), 'dark' => __('Dark')];
    }

    /** @return array<string, string> */
    public static function fontOptions(): array
    {
        return [
            'inter' => 'Inter', 'system' => __('System default'), 'arial' => 'Arial', 'verdana' => 'Verdana',
            'georgia' => 'Georgia', 'playfair' => 'Playfair Display',
        ];
    }

    /** @return array<string, string> */
    private static function defaults(): array
    {
        return [
            'show_header' => '1',
            'show_subscribe' => '1',
            'header_title' => '',
            'header_subtitle' => __('Kingdom Calendar'),
            'calendar_heading' => '',
            'artwork' => 'icon',
            'header_icon' => 'court-crown',
            'logo_key' => '',
            'intro' => __('Tourneys, courts, classes, and gatherings. All in one place.'),
            'show_footer' => '1',
            'footer_title' => __('About this calendar'),
            'footer_body' => __(
                'Events are published by the kingdom calendar staff. Empty weekends show no published events; '
                . 'they do not guarantee that a date is available to book.',
            ) . "\n\n" . __('Use Subscribe to Calendar to follow published updates in your calendar app.'),
            'theme' => 'classic',
            'heading_font' => 'georgia',
            'body_font' => 'inter',
            'background' => '',
            'panel' => '',
            'text' => '',
            'accent' => '',
            'embed_origins' => '',
            'embed_header' => '0',
            'embed_intro' => '0',
            'embed_footer' => '0',
        ];
    }

    /** Read with defaults and without initializing a database row. */
    public function load(): array
    {
        $value = $this->settings->getSetting(self::KEY);
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $fields = self::defaults();
        foreach (array_intersect_key(is_array($decoded) ? $decoded : [], $fields) as $field => $entry) {
            if (is_string($entry)) {
                $fields[$field] = $entry;
            }
        }

        return $fields;
    }

    /** @return array<string, string> */
    public function submittedFields(array $data, array $current): array
    {
        foreach (array_diff_key($current, ['logo_key' => true]) as $name => $value) {
            if (in_array($name, self::CHECKBOXES, true)) {
                $current[$name] = in_array($data[$name] ?? null, ['1', 1, true], true) ? '1' : '0';
            } elseif (array_key_exists($name, $data)) {
                $current[$name] = is_string($data[$name]) ? trim($data[$name]) : '';
            }
        }

        return $current;
    }

    /**
     * Validate the entire form before publishing artwork or changing the saved settings.
     *
     * @return array<string, array<string, string>> Field errors, or an empty array on success
     */
    public function save(array $fields, ?UploadedFileInterface $upload): array
    {
        $errors = $this->validate($fields);
        $hasUpload = $upload !== null && $upload->getError() !== UPLOAD_ERR_NO_FILE;
        $logo = null;
        if ($hasUpload) {
            if ($fields['artwork'] !== 'logo') {
                $errors['logo']['source'] = __('Select Uploaded logo to use this image.');
            } else {
                $logo = PublicAssetService::normalizePngUpload($upload, 2 * 1024 * 1024, 1024);
                if ($logo === null) {
                    $errors['logo']['image'] = __('Upload a PNG or WebP up to 2 MB and 1024 × 1024 pixels.');
                }
            }
        } elseif ($fields['artwork'] === 'logo' && !PublicAssetService::isTenantAssetKey($fields['logo_key'])) {
            $errors['logo']['required'] = __('Choose a logo to upload.');
        }
        if ($errors !== []) {
            return $errors;
        }

        if ($logo !== null) {
            try {
                $fields['logo_key'] = $this->assets->publishPng($logo);
            } catch (FilesystemException | RuntimeException $exception) {
                Log::error('Calendar logo publication failed: ' . $exception::class);

                return ['logo' => ['storage' => __('The logo could not be uploaded. Please try again.')]];
            }
        }
        $fields['header_icon'] = IconCatalog::canonical($fields['header_icon']) ?? '';
        if (!$this->settings->updateSetting(self::KEY, 'json', json_encode($fields, JSON_THROW_ON_ERROR))) {
            return ['_settings' => ['save' => __('The calendar settings could not be saved. Please try again.')]];
        }

        return [];
    }

    /** @return array<string, array<string, string>> */
    private function validate(array $fields): array
    {
        $errors = [];
        $limits = [
            'header_title' => 120, 'header_subtitle' => 120, 'calendar_heading' => 120,
            'header_icon' => 120, 'footer_title' => 120, 'intro' => 12000, 'footer_body' => 12000,
        ];
        foreach ($limits as $field => $limit) {
            if (!mb_check_encoding($fields[$field], 'UTF-8')) {
                $errors[$field]['encoding'] = __('Enter valid text.');
            } elseif (mb_strlen($fields[$field]) > $limit) {
                $errors[$field]['length'] = __('Use {0} characters or fewer.', $limit);
            }
        }
        foreach (self::COLOR_FIELDS as $field) {
            if ($fields[$field] !== '' && !self::isColor($fields[$field])) {
                $errors[$field]['color'] = __('Enter a six-digit hex color, such as #254D77.');
            }
        }
        $options = [
            'theme' => self::PALETTES, 'heading_font' => self::FONTS, 'body_font' => self::FONTS,
            'artwork' => ['icon' => true, 'logo' => true, 'none' => true],
        ];
        foreach ($options as $field => $choices) {
            if (!isset($choices[$fields[$field]])) {
                $errors[$field]['option'] = __('Choose an option from the list.');
            }
        }
        if ($fields['artwork'] === 'icon' && IconCatalog::canonical($fields['header_icon']) === null) {
            $errors['header_icon']['icon'] = __('Choose a KMP icon or select a different header artwork option.');
        }
        if (self::frameOrigins($fields['embed_origins']) === null) {
            $errors['embed_origins']['origin'] = __(
                'Enter exact HTTPS origins, one per line, such as https://kingdom.example.',
            );
        }
        foreach (self::lowContrastFields(self::palette($fields)) as $field) {
            $errors[$field]['contrast'] = __(
                'Use a color with at least 4.5:1 contrast against the calendar backgrounds.',
            );
        }

        return $errors;
    }

    /** @return list<string>|null Exact HTTPS origins, or null for an invalid list. */
    public static function frameOrigins(string $value): ?array
    {
        if (strlen($value) > 2048) {
            return null;
        }
        $origins = [];
        foreach (preg_split('/\R/', $value) as $line) {
            $origin = strtolower(trim($line));
            if ($origin === '') {
                continue;
            }
            if (
                !preg_match(
                    '~^https://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+'
                    . '[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?(?::([1-9]\d{0,4}))?/?$~D',
                    $origin,
                    $parts,
                ) || (isset($parts[1]) && (int)$parts[1] > 65535)
            ) {
                return null;
            }
            $origins[] = rtrim($origin, '/');
        }

        return array_values(array_unique($origins));
    }

    /** Resolve only a logo published by the active tenant. */
    public static function logoUrl(array $fields): ?string
    {
        return PublicAssetService::isTenantAssetKey($fields['logo_key'])
            ? PublicAssetService::url($fields['logo_key'])
            : null;
    }

    /** Render only validated colors and known font stacks into the stylesheet. */
    public static function style(array $fields): string
    {
        $palette = self::palette($fields);
        if (self::lowContrastFields($palette) !== []) {
            $palette = self::PALETTES[$fields['theme']] ?? self::PALETTES['classic'];
        }
        foreach (['background', 'panel', 'tint'] as $background) {
            if (GatheringActivityPresentationService::contrastRatio($palette['muted'], $palette[$background]) < 4.5) {
                $palette['muted'] = $palette['text'];
                break;
            }
        }
        $lightAccentText = GatheringActivityPresentationService::contrastRatio('#ffffff', $palette['accent']) >= 4.5;
        $darkSurface = GatheringActivityPresentationService::contrastRatio('#ffffff', $palette['panel']) >= 4.5;
        $variables = [
            'bg' => $palette['background'], 'surface' => $palette['panel'], 'ink' => $palette['text'],
            'muted' => $palette['muted'], 'accent' => $palette['accent'], 'gold' => $palette['accent'],
            'accent-contrast' => $lightAccentText ? '#fff' : '#000',
            'rule' => $palette['rule'], 'tint' => $palette['tint'],
            'color-scheme' => $darkSurface ? 'dark' : 'light',
            'font-body' => self::FONTS[$fields['body_font']] ?? self::FONTS['inter'],
            'font-display' => self::FONTS[$fields['heading_font']] ?? self::FONTS['georgia'],
        ];
        $css = '';
        foreach ($variables as $name => $value) {
            $css .= "--kc-$name:$value;";
        }

        return $css;
    }

    /** Accept only literal six-digit CSS colors. */
    private static function isColor(string $value): bool
    {
        return (bool)preg_match('/^#[0-9a-f]{6}$/Di', $value);
    }

    /** @return array<string, string> */
    private static function palette(array $fields): array
    {
        $preset = self::PALETTES[$fields['theme']] ?? self::PALETTES['classic'];
        $overrides = [];
        foreach (self::COLOR_FIELDS as $field) {
            if (self::isColor($fields[$field])) {
                $overrides[$field] = $fields[$field];
            }
        }
        $palette = $overrides + $preset;
        if (isset($overrides['background']) || isset($overrides['panel'])) {
            $palette['tint'] = $palette['background'];
        }

        return $palette;
    }

    /** @return list<string> */
    private static function lowContrastFields(array $palette): array
    {
        $fields = [];
        foreach (['text', 'accent'] as $field) {
            foreach (['background', 'panel', 'tint'] as $background) {
                $contrast = GatheringActivityPresentationService::contrastRatio(
                    $palette[$field],
                    $palette[$background],
                );
                if ($contrast < 4.5) {
                    $fields[] = $field;
                    break;
                }
            }
        }

        return $fields;
    }
}
