<?php
declare(strict_types=1);

namespace App\View\Helper;

use BootstrapUI\View\Helper\FormHelper;

/** Bootstrap forms with the shared registered icon option for buttons and action links. */
class KmpFormHelper extends FormHelper
{
    use IconOptionsTrait;

    /** @inheritDoc */
    public function postLink(string $title, array|string|null $url = null, array $options = []): string
    {
        [$title, $options] = $this->withIcon($title, $options);

        return parent::postLink($title, $url, $options);
    }

    /** @inheritDoc */
    public function button(string $title, array $options = []): string
    {
        [$title, $options] = $this->withIcon($title, $options);

        return parent::button($title, $options);
    }
}
