<?php
declare(strict_types=1);

/**
 * KMP URL Routing Configuration
 *
 * This file defines the URL routing rules for the Kingdom Management Portal (KMP).
 * It establishes how URLs map to controllers and actions, enabling clean and 
 * intuitive navigation throughout the application.
 *
 * KMP Routing Strategy:
 * - RESTful routing conventions for consistent API design
 * - Dashed URLs for improved readability (e.g., /member-roles)
 * - Extension-based content negotiation (JSON, PDF, CSV)
 * - Scoped routing for organized URL structures
 * - Middleware integration for image processing and security
 *
 * Route Types in KMP:
 * 1. Core application routes (members, branches, roles, etc.)
 * 2. Plugin routes (activities, awards, officers)
 * 3. API routes with JSON responses
 * 4. File download routes (PDF reports, CSV exports)
 * 5. Image processing routes with Glide middleware
 * 6. Utility routes (keepalive, manifests)
 *
 * Security Considerations:
 * - Secure URL signing for image manipulation
 * - Extension whitelisting for content types
 * - Middleware-based access control
 * - CSRF protection through routing configuration
 *
 * Performance Features:
 * - Image caching and optimization via Glide
 * - Route caching in production environments
 * - Fallback routing for development flexibility
 *
 * This routing configuration is loaded within Application::routes() and works
 * with the RouteBuilder instance to establish URL patterns and route matching.
 *
 * @see src/Application.php Application::routes() method
 * @see https://book.cakephp.org/5/en/development/routing.html CakePHP Routing Guide
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/**
 * KMP Route Configuration Function
 *
 * This function is executed within the Application class context, allowing access
 * to application instance through $this if needed. The RouteBuilder instance
 * provides methods for defining URL patterns and their corresponding handlers.
 *
 * @param RouteBuilder $routes The route builder instance for defining routes
 * @return void
 */
return function (RouteBuilder $routes): void {
    /**
     * Default Route Class Configuration
     *
     * Sets DashedRoute as the default route class for consistent URL formatting.
     * DashedRoute converts controller and action names to dashed format:
     * - MemberRolesController becomes /member-roles
     * - addPermission action becomes /add-permission
     *
     * Available Route Classes:
     * - Route: Basic routing without inflection (inconsistent casing)
     * - InflectedRoute: Applies inflection rules for URL generation
     * - DashedRoute: Converts CamelCase to dashed-format (recommended)
     *
     * KMP Benefits of DashedRoute:
     * - Consistent, readable URLs across the application
     * - SEO-friendly URL structure
     * - Matches modern web conventions
     * - Easier to type and remember for users
     *
     * @example MembersController::viewProfile() → /members/view-profile
     * @example WarrantPeriodsController::addPeriod() → /warrant-periods/add-period
     */
    $routes->setRouteClass(DashedRoute::class);

    /**
     * Main Application Scope
     *
     * Defines routes within the root scope ("/") for core KMP functionality.
     * This scope handles the primary application routes including:
     * - Homepage and static pages
     * - Core entity management (members, branches, roles, etc.)
     * - RESTful API endpoints
     * - File downloads and exports
     *
     * Extension Support:
     * - json: API responses and AJAX requests
     * - pdf: Generated reports and documents
     * - csv: Data exports and bulk operations
     *
     * @scope "/" Root application scope
     */
    $routes->scope("/", function (RouteBuilder $builder): void {
        /**
         * Content Negotiation Extensions
         * 
         * Enables automatic content type detection based on URL extensions.
         * Allows the same endpoint to serve different formats:
         * - /members.json → JSON API response
         * - /reports/members.pdf → PDF download
         * - /members/export.csv → CSV download
         */
        $builder->setExtensions(["json", "pdf", "csv"]);

        /**
         * Homepage Route
         * 
         * Maps the root URL to the Pages controller's display action.
         * Serves the main dashboard/landing page for authenticated users.
         * 
         * @route "/" → PagesController::display('home')
         * @template templates/Pages/home.php
         */
        $builder->connect("/", [
            "controller" => "Pages",
            "action" => "display",
            "home",
        ]);

        /**
         * Static Pages Routes
         * 
         * Handles static content pages and informational displays.
         * Uses wildcard matching to pass page names as parameters.
         * 
         * @route "/pages/*" → PagesController::display($page)
         * @example "/pages/about" → display('about')
         * @example "/pages/help" → display('help')
         */
        $builder->connect("/pages/*", "Pages::display");
        
        /**
         * Progressive Web App Manifest Route
         * 
         * Serves the web app manifest for PWA functionality.
         * Enables mobile users to install KMP as a native app experience.
         * 
         * @route "/members/card.webmanifest/*" → PagesController::Webmanifest()
         * @contentType application/manifest+json
         */
        $builder->connect("/members/card.webmanifest/*", "Pages::Webmanifest");

        /**
         * RESTful Fallback Routes
         * 
         * Provides automatic routing for all controllers using RESTful conventions.
         * Creates standard CRUD routes for each controller:
         * 
         * Generated Routes:
         * - GET /{controller} → index()
         * - GET /{controller}/view/{id} → view($id)
         * - GET /{controller}/add → add()
         * - POST /{controller}/add → add() (form submission)
         * - GET /{controller}/edit/{id} → edit($id)
         * - PUT/PATCH /{controller}/edit/{id} → edit($id) (form submission)
         * - DELETE /{controller}/delete/{id} → delete($id)
         * 
         * KMP Controller Examples:
         * - /members → MembersController::index()
         * - /branches/view/1 → BranchesController::view(1)
         * - /roles/add → RolesController::add()
         * - /warrants/edit/5 → WarrantsController::edit(5)
         * 
         * Plugin Integration:
         * Fallback routes also work with plugin controllers automatically.
         * Plugin routes follow the pattern: /{plugin}/{controller}/{action}
         * 
         * @note Remove fallback routes in production for better performance
         * @note Define explicit routes for better security and control
         */
        $builder->fallbacks();
    });
    /**
     * Session Keep-Alive Route
     * 
     * Provides AJAX endpoint for extending user sessions without full page reload.
     * Called by JavaScript to prevent session timeout during active use.
     * 
     * Security Features:
     * - CSRF protection required
     * - Authentication required
     * - Rate limiting recommended
     * 
     * @route "/keepalive" → SessionsController::keepalive()
     * @method POST
     * @returns JSON response with session status
     * @example Used by session-extender-controller.js
     */
    $routes->connect('/keepalive', ['controller' => 'Sessions', 'action' => 'keepalive']);
    
    /**
     * Image Processing Scope
     * 
     * Dedicated scope for image manipulation and serving using Glide middleware.
     * Provides on-the-fly image resizing, cropping, and optimization for:
     * - Member profile photos
     * - Award images and badges
     * - Branch insignia and logos
     * - Activity documentation photos
     * 
     * Glide Features:
     * - Dynamic image resizing and cropping
     * - Format conversion (JPEG, PNG, WebP)
     * - Quality optimization for web delivery
     * - Secure URL signing to prevent abuse
     * - Efficient caching system
     * 
     * Security Measures:
     * - Secure URLs with cryptographic signing
     * - Source path restrictions
     * - Allowed parameter filtering
     * - Cache duration controls
     * 
     * @scope "/images" Image processing scope
     * @middleware GlideMiddleware
     * @see https://glide.thephpleague.com/ Glide Documentation
     */
    $routes->scope('/images', function ($routes) {
        /**
         * Glide Middleware Registration
         * 
         * Configures the Glide image manipulation middleware with KMP-specific settings.
         * Handles image processing requests before they reach any controller.
         */
        $routes->registerMiddleware('glide', new \ADmad\Glide\Middleware\GlideMiddleware([
            /**
             * Path Filter
             * 
             * URL path prefix filter for middleware activation.
             * Set to null to process all requests in this scope.
             * 
             * @var string|null Path prefix for middleware activation
             */
            'path' => null,

            /**
             * Glide Server Configuration
             * 
             * Core settings for image processing server instance.
             * Defines source, cache, and processing parameters.
             * 
             * @see http://glide.thephpleague.com/1.0/config/setup/
             */
            'server' => [
                /**
                 * Image Source Directory
                 * 
                 * Source location for original images.
                 * Points to uploaded images directory outside webroot for security.
                 * 
                 * @var string Source directory path
                 */
                'source' => WWW_ROOT . '../images/uploaded',

                /**
                 * Image Cache Directory
                 * 
                 * Cache location for processed images.
                 * Separate from source to prevent cache poisoning.
                 * 
                 * @var string Cache directory path
                 */
                'cache' => WWW_ROOT . '../images/cache',

                /**
                 * Base URL Configuration
                 * 
                 * URL prefix to be removed when mapping to source files.
                 * Allows clean URLs while maintaining security.
                 * 
                 * @var string URL prefix to strip from requests
                 */
                'base_url' => '/images/',

                /**
                 * Response Handler
                 * 
                 * Custom response class for serving images.
                 * Uses default PSR response factory if not specified.
                 * 
                 * @var mixed|null Custom response handler
                 */
                'response' => null,
            ],

            /**
             * Security Configuration
             * 
             * Prevents URL manipulation and unauthorized image processing.
             * Uses cryptographic signatures to validate requests.
             * 
             * @see http://glide.thephpleague.com/1.0/config/security/
             */
            'security' => [
                /**
                 * Secure URLs Enable
                 * 
                 * Requires cryptographically signed URLs to prevent parameter manipulation.
                 * Protects against resource exhaustion and unauthorized processing.
                 * 
                 * @var bool Enable secure URL signing
                 */
                'secureUrls' => true,

                /**
                 * Signing Key
                 * 
                 * Cryptographic key for URL signing and validation.
                 * Uses CakePHP security salt if not specified.
                 * 
                 * @var string|null Signing key (uses Security::salt() if null)
                 */
                'signKey' => null,
            ],

            /**
             * Cache Duration
             * 
             * How long processed images should be cached.
             * Balances performance with storage usage.
             * 
             * @var string Cache duration (strtotime format)
             */
            'cacheTime' => '+1 days',

            /**
             * Response Headers
             * 
             * Additional HTTP headers to include with image responses.
             * Useful for cache control and security headers.
             * 
             * @var array|null Custom response headers
             */
            'headers' => null,

            /**
             * Allowed Parameters
             * 
             * Whitelist of allowed query parameters for image manipulation.
             * Restricts available operations to prevent abuse.
             * 
             * Common Parameters:
             * - w: width
             * - h: height
             * - fit: resize mode
             * - crop: crop coordinates
             * - q: quality
             * - fm: format
             * 
             * @var array|null Allowed parameter names (null = all allowed)
             * @example ['w', 'h', 'fit', 'q'] for basic resizing only
             */
            'allowedParams' => null
        ]));

        /**
         * Apply Glide Middleware
         * 
         * Activates the Glide middleware for all routes in this scope.
         * Processes image manipulation before routing to any controller.
         */
        $routes->applyMiddleware('glide');

        /**
         * Catch-All Image Route
         * 
         * Matches all requests in the /images scope.
         * Allows Glide middleware to handle any image path and parameters.
         * 
         * @route "/images/*" → Glide middleware processing
         * @example "/images/members/photo.jpg?w=150&h=150" → Resized photo
         * @example "/images/awards/badge.png?q=80&fm=webp" → Optimized badge
         */
        $routes->connect('/*');
    });
    
    /**
     * Global Extension Configuration
     * 
     * Sets default file extensions for routes outside specific scopes.
     * Enables content negotiation for API endpoints and downloads.
     * 
     * Supported Extensions:
     * - json: API responses and AJAX data
     * - pdf: Generated reports and documents
     * 
     * @var array Supported file extensions
     * @example "/api/members.json" → JSON member data
     * @example "/reports/activity.pdf" → PDF report download
     */
    $routes->setExtensions(["json", "pdf"]);
};