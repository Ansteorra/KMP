<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\AppController as BaseController;

/**
 * Awards Plugin AppController - Base controller for award management system.
 * 
 * Extends main KMP AppController with Awards-specific component configuration.
 * Establishes security baseline for all award management controllers.
 * 
 * @package Awards\Controller
 * @see \App\Controller\AppController Parent controller
 */
class AppController extends BaseController
{
    /**
     * Initialize Awards Plugin Base Controller.
     * 
     * Loads Authentication, Authorization, and Flash components.
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
