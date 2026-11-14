<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Services\IconCatalog;
use Cake\View\Helper;

/** Shared catalog renderer for server-rendered views. */
class IconHelper extends Helper
{
    /**
     * @param string|null $id Canonical ID or exact legacy alias
     * @param array<string, mixed> $options Presentation and accessible name
     * @return string
     */
    public function render(?string $id, array $options = []): string
    {
        return IconCatalog::render($id, $options);
    }
}
