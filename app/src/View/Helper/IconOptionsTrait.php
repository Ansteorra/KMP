<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Services\IconCatalog;

/** The icon option keeps link/button text escaping and accessible naming in one place. */
trait IconOptionsTrait
{
    /**
     * @param string $title Control text
     * @param array<string, mixed> $options Helper options
     * @return array{string, array<string, mixed>}
     */
    protected function withIcon(string $title, array $options): array
    {
        if (!isset($options['icon'])) {
            return [$title, $options];
        }
        $icon = $options['icon'];
        unset($options['icon']);
        if (($options['escape'] ?? true) !== false && ($options['escapeTitle'] ?? true) !== false) {
            $title = h($title);
        }
        if ($title === '' && !isset($options['aria-label'])) {
            $options['aria-label'] = $options['title']
                ?? ucfirst(str_replace('-', ' ', IconCatalog::canonical($icon) ?? 'Help'));
        }
        $options['escapeTitle'] = false;

        return [IconCatalog::render($icon, ['class' => $title !== '' ? 'me-1' : '']) . $title, $options];
    }
}
