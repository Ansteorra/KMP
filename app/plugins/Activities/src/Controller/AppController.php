<?php

declare(strict_types=1);

namespace Activities\Controller;

use App\Controller\AppController as BaseController;
use Cake\Event\EventInterface;
use Psr\Http\Message\UriInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

/**
 * Activities Plugin Base Controller
 *
 * Foundation for all Activities plugin controllers providing shared security configuration,
 * component loading, and integration patterns for activity management and authorization workflows.
 *
 * Child controllers: ActivitiesController, AuthorizationsController, AuthorizationApprovalsController,
 * ActivityGroupsController, ReportsController
 *
 * @package Activities\Controller
 */
class AppController extends BaseController
{
    /**
     * Initialize plugin controllers with authentication, authorization, and flash components.
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
