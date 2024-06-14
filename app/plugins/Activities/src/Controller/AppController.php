<?php

declare(strict_types=1);

namespace Activities\Controller;

use App\Controller\AppController as BaseController;

class AppController extends BaseController
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent("Authentication.Authentication");
        $this->loadComponent("Authorization.Authorization");
        $this->loadComponent("Flash");

        // $this->appSettings = ServiceProvider::getContainer()->get(AppSettingsService::class);

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        // $this->loadComponent('FormProtection');
    }
}
