<?php

declare(strict_types=1);

/**
 * Officers Plugin - Base Controller
 *
 * Provides security baseline and common configuration for Officers plugin controllers.
 */

namespace Officers\Controller;

use App\Controller\AppController as BaseController;

/**
 * Officers Plugin Base Controller
 *
 * Extends application AppController to establish security baseline for all Officers controllers.
 */
class AppController extends BaseController
{
    /**
     * Initialize controller with Officers plugin configuration.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Inherit complete KMP security framework and Officers plugin integration
        parent::initialize();

        // Officers plugin security baseline established through inheritance:
        // - Authentication.Authentication component (user identity management)
        // - Authorization.Authorization component (permission checking)
        // - Flash component (standardized user feedback)
        // - Plugin validation (Officers plugin must be enabled)
        // - Navigation history (breadcrumb and back navigation support)
        // - View cell integration (Officers plugin UI components)
        // - Request processing (CSV export, Turbo Frame, AJAX support)

        // Note: Additional Officers-specific components can be loaded here
        // if needed for shared functionality across all Officers controllers

        // Service container access is available for Officers plugin services:
        // - OfficerManagerInterface for officer assignment business logic
        // - ActiveWindowManager for temporal assignment management
        // - WarrantManager for warrant lifecycle operations

        // Child controllers should override this method to add:
        // - Controller-specific authorization configuration
        // - Additional component loading (Paginator, RequestHandler, etc.)
        // - Service injection for controller-specific business logic
    }
}
