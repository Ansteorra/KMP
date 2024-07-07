<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\View;

use BootstrapUI\View\UIViewTrait;
use Cake\View\View;
use Cake\I18n\DateTime;

/**
 * Application View
 *
 * Your application's default view class
 *
 * @link https://book.cakephp.org/4/en/views.html#the-app-view
 */
class AppView extends View
{
    use UIViewTrait;

    /**
     * Initialization hook method.
     */
    public function initialize(): void
    {
        parent::initialize();

        // Call the initializeUI method from UIViewTrait
        $this->initializeUI(["layout" => false]);
        $this->loadHelper('AssetMix.AssetMix');
        $this->loadHelper("Authentication.Identity");
        $this->loadHelper("Bootstrap.Modal");
        $this->loadHelper("Bootstrap.Navbar");
        $this->loadHelper("Url");
        //$this->loadHelper("AssetCompress.AssetCompress");
        $this->loadHelper("Kmp");
        // All option values should match the corresponding options for `GlideFilter`.
        $this->loadHelper('ADmad/Glide.Glide', [
            // Base URL.
            'baseUrl' => '/images/',
            // Whether to generate secure URLs.
            'secureUrls' => true,
            // Signing key to use when generating secure URLs.
            'signKey' => null,
        ]);
    }
}