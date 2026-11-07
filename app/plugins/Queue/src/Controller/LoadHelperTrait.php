<?php

declare(strict_types=1);

namespace Queue\Controller;

use Templating\View\Helper\TemplatingHelper;

trait LoadHelperTrait
{

	/**
	 * @return void
	 */
	protected function loadHelpers(): void
	{
		$helpers = [
			'Shim.Configure',
			'Tools.Format',
			'Tools.Text',
			'Tools.Time',
			'Icon',
		];
		if (class_exists(TemplatingHelper::class)) {
			$helpers[] = 'Templating.Templating';
		}

		$this->viewBuilder()->addHelpers($helpers);
	}
}
