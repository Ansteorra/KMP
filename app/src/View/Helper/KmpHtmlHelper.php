<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Services\IconCatalog;
use BootstrapUI\View\Helper\HtmlHelper;

/** BootstrapUI's supported icon extension point delegates to the KMP catalog. */
class KmpHtmlHelper extends HtmlHelper
{
    use IconOptionsTrait;

    /** @inheritDoc */
    public function link(array|string $title, array|string|null $url = null, array $options = []): string
    {
        if (is_string($title)) {
            [$title, $options] = $this->withIcon($title, $options);
        }

        return parent::link($title, $url, $options);
    }

    /** @inheritDoc */
    public function icon(string $name, array $options = []): string
    {
        return IconCatalog::render($name, $options);
    }
}
