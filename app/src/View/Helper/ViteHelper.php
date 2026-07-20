<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use RuntimeException;

/**
 * Helper for resolving Vite-built assets via the build manifest.
 *
 * Reads `.vite/manifest.json` from webroot and generates properly
 * versioned `<script>` and `<link>` tags for production builds.
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\UrlHelper $Url
 */
class ViteHelper extends Helper
{
    protected array $helpers = ['Html', 'Url'];

    /**
     * Cached manifest data.
     */
    private static ?array $manifest = null;

    /**
     * Render a `<link>` tag for a Vite-built CSS asset.
     *
     * @param string $name Logical name (e.g., 'app', 'signin', 'dashboard')
     * @param array<mixed> $options HtmlHelper::css options
     * @return string|null
     */
    public function css(string $name, array $options = []): ?string
    {
        $manifest = $this->getManifest();

        // Try direct CSS entry first (e.g., "assets/css/app.css")
        $candidates = [
            "assets/css/{$name}.css",
            "plugins/Waivers/assets/css/{$name}.css",
            "node_modules/drawflow/dist/{$name}.min.css",
        ];

        foreach ($candidates as $key) {
            if (isset($manifest[$key])) {
                $file = '/' . $manifest[$key]['file'];
                return $this->Html->css($file, $options);
            }
        }

        // Fallback: search manifest for matching name in the file path
        foreach ($manifest as $key => $entry) {
            if (
                str_ends_with($key, '.css') &&
                str_contains($key, $name)
            ) {
                $file = '/' . $entry['file'];
                return $this->Html->css($file, $options);
            }
        }

        throw new RuntimeException("Vite CSS asset not found: {$name}");
    }

    /**
     * Render a `<script>` tag for a Vite-built JS asset.
     *
     * @param string $name Logical name (e.g., 'index', 'controllers')
     * @param array<mixed> $options HtmlHelper::script options
     * @return string|null
     */
    public function script(string $name, array $options = []): ?string
    {
        $defaults = ['type' => 'module'];
        $options += $defaults;

        $manifest = $this->getManifest();

        // Direct key match
        $key = "assets/js/{$name}.js";
        if (isset($manifest[$key])) {
            $file = '/' . $manifest[$key]['file'];
            return $this->Html->script($file, $options);
        }

        // Fallback: match by manifest entry "name" field
        foreach ($manifest as $entry) {
            if (
                ($entry['name'] ?? '') === $name &&
                ($entry['isEntry'] ?? false) &&
                str_ends_with($entry['file'] ?? '', '.js')
            ) {
                $file = '/' . $entry['file'];
                return $this->Html->script($file, $options);
            }
        }

        throw new RuntimeException("Vite JS asset not found: {$name}");
    }

    /**
     * Get the versioned URL for a script asset (for service worker cache lists).
     *
     * @param string $name Logical script name
     * @return string Versioned URL path
     */
    public function getScriptUrl(string $name): string
    {
        $manifest = $this->getManifest();
        $key = "assets/js/{$name}.js";

        if (isset($manifest[$key])) {
            return '/' . $manifest[$key]['file'];
        }

        foreach ($manifest as $entry) {
            if (
                ($entry['name'] ?? '') === $name &&
                ($entry['isEntry'] ?? false) &&
                str_ends_with($entry['file'] ?? '', '.js')
            ) {
                return '/' . $entry['file'];
            }
        }

        throw new RuntimeException("Vite JS asset not found: {$name}");
    }

    /**
     * Get the versioned URL for a CSS asset (for service worker cache lists).
     *
     * @param string $name Logical CSS name
     * @return string Versioned URL path
     */
    public function getStyleUrl(string $name): string
    {
        $manifest = $this->getManifest();

        $candidates = [
            "assets/css/{$name}.css",
            "plugins/Waivers/assets/css/{$name}.css",
            "node_modules/drawflow/dist/{$name}.min.css",
        ];

        foreach ($candidates as $key) {
            if (isset($manifest[$key])) {
                return '/' . $manifest[$key]['file'];
            }
        }

        foreach ($manifest as $key => $entry) {
            if (str_ends_with($key, '.css') && str_contains($key, $name)) {
                return '/' . $entry['file'];
            }
        }

        throw new RuntimeException("Vite CSS asset not found: {$name}");
    }

    /**
     * Load and cache the Vite manifest.
     *
     * @return array<string, array<string, mixed>>
     * @throws \RuntimeException If manifest file is missing
     */
    private function getManifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = WWW_ROOT . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!file_exists($path)) {
            throw new RuntimeException(
                'Vite manifest not found. Run `npm run build` first. '
                . "Expected: {$path}",
            );
        }

        $content = json_decode(file_get_contents($path) ?: '', true);

        if (!is_array($content)) {
            throw new RuntimeException('Vite manifest is not valid JSON.');
        }

        self::$manifest = $content;

        return self::$manifest;
    }

    /**
     * Reset the cached manifest (useful for testing).
     */
    public static function reset(): void
    {
        self::$manifest = null;
    }
}
