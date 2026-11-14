<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Core\Configure;
use Cake\Log\Log;

/** Exact identifiers and trusted local assets shared by every application icon surface. */
final class IconCatalog
{
    public const FALLBACK = 'help-circle';

    private const LEGACY_SIZE = '/^bi-(?:2xs|xs|sm|lg|xl|2xl|[1-9]x|10x)$/D';

    /** @return array<string, mixed> */
    public static function data(): array
    {
        static $data;

        return $data ??= require ROOT . DS . 'resources' . DS . 'icons' . DS . 'resolver.php';
    }

    /** Resolve exact IDs/aliases, including one icon in a legacy compound class string. */
    public static function canonical(?string $value): ?string
    {
        $data = self::data();
        $value = trim($value ?? '');
        if (isset($data['entries'][$value])) {
            return $value;
        }
        if (isset($data['aliases'][$value])) {
            return $data['aliases'][$value];
        }
        if (str_contains($value, ' ')) {
            $tokens = preg_split('/\s+/', $value);
            $icons = array_values(array_filter($tokens, static fn($token) => str_starts_with($token, 'bi-')
                && !preg_match(self::LEGACY_SIZE, $token)));
            if (count($icons) === 1 && isset($data['aliases'][$icons[0]])) {
                return $data['aliases'][$icons[0]];
            }
        }

        return null;
    }

    /** @return list<string> Canonical IDs for selection/validation. */
    public static function ids(): array
    {
        return array_keys(self::data()['entries']);
    }

    /**
     * Compatibility boundary for persisted header/footer class settings. New settings supply a separate icon ID.
     *
     * @param string $classes Stored utility/legacy icon classes
     * @param string|null $icon Explicit canonical identifier
     * @return array{class: string, icon?: string}
     */
    public static function controlOptions(string $classes, ?string $icon = null): array
    {
        $tokens = preg_split('/\s+/', trim($classes));
        $legacy = array_values(array_filter($tokens, static fn($token) => str_starts_with($token, 'bi-')
            && !preg_match(self::LEGACY_SIZE, $token)));
        $utilities = array_filter($tokens, static fn($token) => $token !== 'bi'
            && !str_starts_with($token, 'bi-') && preg_match('/^[a-zA-Z0-9_:-]+$/D', $token));
        $options = ['class' => implode(' ', $utilities)];
        foreach ($tokens as $token) {
            if (preg_match(self::LEGACY_SIZE, $token)) {
                $options['class'] .= ' kmp-icon-' . substr($token, 3);
            }
        }
        if ($icon !== null && $icon !== '') {
            $options['icon'] = $icon;
        } elseif ($legacy !== []) {
            $options['icon'] = count($legacy) === 1 ? $legacy[0] : self::FALLBACK;
        }

        return $options;
    }

    /** Trusted URL only: never use an input identifier as a filename. */
    public static function url(string $id): string
    {
        $data = self::data();
        $canonical = self::canonical($id) ?? self::FALLBACK;

        return '/kmp-icons/' . $data['revision'] . '/' . $data['entries'][$canonical];
    }

    /**
     * Decorative by default. Use label for informative icons; label the enclosing icon-only control too.
     *
     * @param string|null $id Registered canonical ID or exact compatibility alias
     * @param array<string, mixed> $options size (CSS pixels), label, class, title, aria-* and data-* attributes
     * @return string
     */
    public static function render(?string $id, array $options = []): string
    {
        $canonical = self::canonical($id);
        if ($canonical === null && Configure::read('debug')) {
            static $reported = [];
            $key = hash('sha256', $id ?? '');
            if (!isset($reported[$key])) {
                Log::debug('Unknown KMP icon: ' . json_encode($id));
                $reported[$key] = true;
            }
        }
        $canonical ??= self::FALLBACK;
        $label = $options['label'] ?? $options['aria-label'] ?? null;
        $attributes = ['name' => $canonical, 'aria-hidden' => $label ? null : 'true'];
        if ($label) {
            $attributes['role'] = 'img';
            $attributes['aria-label'] = $label;
        }
        $style = '--kmp-icon:url("' . self::url($canonical) . '")';
        if (isset($options['size'])) {
            $style .= ';--kmp-icon-size:' . max(8, min(160, (int)$options['size'])) . 'px';
        }
        $attributes['style'] = $style;
        foreach ($options as $key => $value) {
            if (in_array($key, ['class', 'id', 'title'], true) || preg_match('/^(aria|data)-[a-z0-9-]+$/D', $key)) {
                $attributes[$key] = $value;
            }
        }
        $html = '<kmp-icon';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $escaped = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= ' ' . $key . '="' . $escaped . '"';
            }
        }

        return $html . '></kmp-icon>';
    }
}
