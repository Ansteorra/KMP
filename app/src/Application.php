<?php

declare(strict_types=1);

/**
 * Kingdom Management Portal (KMP) - Main Application Class
 * 
 * This file contains the core Application class that bootstraps the entire KMP system.
 * It handles middleware configuration, dependency injection, authentication setup,
 * authorization configuration, and plugin registration.
 * 
 * The Application class serves as the central orchestrator for:
 * - Middleware stack configuration (security, CSRF, routing, etc.)
 * - Authentication and authorization service setup
 * - Dependency injection container registration
 * - Plugin system initialization
 * - Core application settings management
 * - Navigation system registration
 * 
 * Architecture Pattern: This follows the Application Service pattern with
 * middleware composition, providing a single entry point for all HTTP requests
 * and establishing the security, routing, and service boundaries.
 * 
 * Security Features:
 * - CSRF protection with secure cookie settings
 * - Comprehensive security headers (CSP, HSTS, XSS protection)
 * - Session-based authentication with form fallback
 * - Role-based authorization with policy resolution
 * - Brute force protection for login attempts
 * 
 * Performance Considerations:
 * - Table locator optimization for CLI vs web contexts
 * - Asset middleware caching configuration
 * - Service container for dependency injection efficiency
 * 
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 * 
 * @package   App
 * @author    KMP Development Team
 * @version   25.01.11.a
 * @see       \Cake\Http\BaseApplication Base application class
 * @see       \Authentication\AuthenticationServiceProviderInterface Authentication provider
 * @see       \Authorization\AuthorizationServiceProviderInterface Authorization provider
 */

namespace App;

// Authentication usings

use App\Services\NavigationRegistry;
use App\Services\CoreNavigationProvider;
use App\Services\ViewCellRegistry;
use App\Services\CoreViewCellProvider;
use App\KMP\KmpIdentityInterface; // Add this line
use App\KMP\StaticHelpers;
// Authorization usings
use App\Policy\ControllerResolver;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ActiveWindowManager\DefaultActiveWindowManager;
use App\Services\AuthorizationService as KmpAuthorizationService;
use App\Services\CsvExportService;
use App\Services\ImpersonationService;
use App\Services\ICalendarService;
use App\Services\WarrantManager\DefaultWarrantManager;
use App\Services\WarrantManager\WarrantManagerInterface;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Middleware\AuthenticationMiddleware;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Exception\ForbiddenException;
use Authorization\Exception\MissingIdentityException;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\OrmResolver;
use Authorization\Policy\ResolverCollection;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventManager;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Kingdom Management Portal Application Class
 *
 * This is the main application class that orchestrates the entire KMP system.
 * It extends CakePHP's BaseApplication to provide KMP-specific functionality
 * including authentication, authorization, middleware configuration, and 
 * dependency injection setup.
 *
 * Key Responsibilities:
 * - Bootstrap application configuration and plugin registration
 * - Configure middleware stack for security, routing, and request handling
 * - Set up authentication service with session and form-based login
 * - Configure authorization service with policy-based access control
 * - Register core services in the dependency injection container
 * - Initialize navigation system for dynamic menu generation
 * - Manage application settings with version-based updates
 *
 * Middleware Stack (in order):
 * 1. ErrorHandlerMiddleware - Exception handling and error pages
 * 2. Security Headers - CSP, HSTS, XSS protection, frame options
 * 3. AssetMiddleware - Static asset serving with caching
 * 4. RoutingMiddleware - URL routing and route matching
 * 5. BodyParserMiddleware - Request body parsing (JSON, XML, etc.)
 * 6. CsrfProtectionMiddleware - CSRF token validation
 * 7. AuthenticationMiddleware - User authentication
 * 8. AuthorizationMiddleware - Permission checking and access control
 * 9. FootprintMiddleware - User activity tracking
 *
 * Security Model:
 * - Session-based authentication with secure cookie settings
 * - Form-based login with brute force protection
 * - Policy-based authorization using ORM and controller resolvers
 * - Comprehensive CSP headers for XSS prevention
 * - HSTS for secure connections
 *
 * Service Container:
 * - ActiveWindowManagerInterface for date-bounded entities
 * - WarrantManagerInterface for warrant lifecycle management
 * - CsvExportService for data export functionality
 *
 * @extends \Cake\Http\BaseApplication<\App\Application>
 * @implements \Authentication\AuthenticationServiceProviderInterface
 * @implements \Authorization\AuthorizationServiceProviderInterface
 * 
 * @see \App\Services\NavigationRegistry Navigation system
 * @see \App\KMP\StaticHelpers Application settings management
 * @see \App\Services\AuthorizationService Custom authorization logic
 * @see \App\Policy\ControllerResolver Policy resolution for controllers
 */
class Application extends BaseApplication implements
    AuthenticationServiceProviderInterface,
    AuthorizationServiceProviderInterface
{
    /**
     * Application Bootstrap Process
     *
     * This method handles the initial application setup and configuration.
     * It performs several critical initialization tasks:
     *
     * 1. **Parent Bootstrap**: Calls CakePHP's parent bootstrap to load
     *    configuration files and perform standard framework initialization
     *
     * 2. **Table Locator Configuration**: Optimizes the ORM table locator
     *    for web requests (CLI requests use different optimization)
     *
     * 3. **Navigation System Registration**: Registers the core navigation
     *    provider that generates the main application menu structure
     *
     * 4. **Application Settings Management**: Implements a version-based
     *    configuration system that automatically updates settings when
     *    the application is upgraded
     *
     * Version-Based Configuration:
     * The system tracks a configuration version and automatically applies
     * new default settings when the version changes. This ensures that
     * application updates don't break existing installations while still
     * providing new configuration options.
     *
     * Settings Categories Managed:
     * - KMP Core Settings (site titles, branding, maintenance mode)
     * - Member Management (verification emails, registration settings)
     * - Email Configuration (system addresses, signatures)
     * - Activity Management (secretary contacts)
     * - Warrant System (approval requirements, checking schedules)
     * - Branch Management (organizational structure types)
     *
     * Performance Notes:
     * - Table locator optimization reduces memory usage in web contexts
     * - Settings are cached after initial load to prevent database queries
     * - Navigation registration uses callback for lazy loading
     *
     * @return void
     * @throws \Exception If core plugins fail to load
     * @see \App\Services\NavigationRegistry::register() Navigation system
     * @see \App\KMP\StaticHelpers::getAppSetting() Settings management
     * @see \App\Services\CoreNavigationProvider::getNavigationItems() Core navigation
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        // Optimize table locator for web requests
        // CLI requests handle this differently to avoid memory issues
        // This prevents fallback class instantiation which can cause performance problems
        if (PHP_SAPI !== 'cli') {
            FactoryLocator::add(
                'Table',
                (new TableLocator())->allowFallbackClass(false),
            );
        }

        // Register core navigation items using the service-based registry
        // This replaces the old event-based system for better performance
        // The callback approach allows for lazy loading of navigation items
        NavigationRegistry::register(
            'core',                                                              // Source identifier
            [],                                                                  // Static navigation items (none for core)
            function ($user, $params) {                                          // Dynamic callback for navigation generation
                return CoreNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register core view cells (mobile menu items, etc.)
        ViewCellRegistry::register(
            'core',
            [],
            [CoreViewCellProvider::class, 'getViewCells']
        );

        // Version-based application configuration management
        // This system allows automatic updates to application settings when KMP is upgraded
        // Each time the version changes, new default settings are applied
        $currentConfigVersion = '25.11.05.a'; // Update this with each configuration change

        $configVersion = StaticHelpers::getAppSetting('KMP.configVersion', '0.0.0', null, true);
        if ($configVersion != $currentConfigVersion) {
            // Update configuration version first
            StaticHelpers::setAppSetting('KMP.configVersion', $currentConfigVersion, null, true);

            // Core KMP Settings - Basic application configuration
            StaticHelpers::getAppSetting('KMP.BranchInitRun', '', null, true);                           // Tracks branch initialization
            StaticHelpers::getAppSetting('KMP.KingdomName', 'please_set', null, true);                  // Primary kingdom identifier
            StaticHelpers::getAppSetting('KMP.LongSiteTitle', 'Kingdom Management Portal', null, true); // Full application name
            StaticHelpers::getAppSetting('KMP.ShortSiteTitle', 'KMP', null, true);                      // Abbreviated name for headers
            StaticHelpers::getAppSetting('KMP.BannerLogo', 'badge.png', null, true);                    // Main site logo
            StaticHelpers::getAppSetting('KMP.Login.Graphic', 'populace_badge.png', null, true);        // Login page graphic
            StaticHelpers::getAppSetting('KMP.EnablePublicRegistration', 'yes', null, true);            // Allow public sign-ups
            StaticHelpers::getAppSetting('KMP.DefaultTimezone', 'America/Chicago', null, true);         // Default timezone for date/time display
            StaticHelpers::getAppSetting('App.version', '0.0.0', null, true);                           // Application version tracking

            // Member Card Display Settings - Visual presentation of member information
            StaticHelpers::getAppSetting('Member.ViewCard.Graphic', 'auth_card_back.gif', null, true);         // Card background image
            StaticHelpers::getAppSetting('Member.ViewCard.HeaderColor', 'gold', null, true);                   // Card header color scheme
            StaticHelpers::getAppSetting('Member.ViewCard.Template', 'view_card', null, true);                 // Desktop card template
            StaticHelpers::getAppSetting('Member.ViewMobileCard.Template', 'view_mobile_card', null, true);    // Mobile card template
            StaticHelpers::getAppSetting('Member.MobileCard.ThemeColor', 'gold', null, true);                  // Mobile theme color
            StaticHelpers::getAppSetting('Member.MobileCard.BgColor', 'gold', null, true);                     // Mobile background color

            // Member Management Email Settings - Contact addresses for various member processes
            StaticHelpers::getAppSetting('Members.AccountVerificationContactEmail', 'please_set', null, true);  // Account verification support
            StaticHelpers::getAppSetting('Members.AccountDisabledContactEmail', 'please_set', null, true);      // Disabled account support
            StaticHelpers::getAppSetting('Members.NewMemberSecretaryEmail', 'member@test.com', null, true);     // New member notifications
            StaticHelpers::getAppSetting('Members.NewMinorSecretaryEmail', 'minorSet@test.com', null, true);    // Minor member notifications

            // Email System Configuration - Global email settings
            StaticHelpers::getAppSetting('Email.SystemEmailFromAddress', 'site@test.com', null, true);  // System sender address
            StaticHelpers::getAppSetting('Email.SiteAdminSignature', 'site', null, true);               // Default email signature

            // Activity Management Settings - Event and activity coordination
            StaticHelpers::getAppSetting('Activity.SecretaryEmail', 'please_set', null, true);  // Activity coordinator email
            StaticHelpers::getAppSetting('Activity.SecretaryName', 'please_set', null, true);   // Activity coordinator name

            // Warrant System Configuration - Officer warrant management
            StaticHelpers::getAppSetting('Warrant.LastCheck', DateTime::now()->subDays(1)->toDateString(), null, true);  // Last warrant validation
            StaticHelpers::getAppSetting('KMP.RequireActiveWarrantForSecurity', 'yes', null, true);                     // Warrant requirement for security roles
            StaticHelpers::getAppSetting('Warrant.RosterApprovalsRequired', '2', null, true);                           // Number of approvals needed for roster changes

            // Help and Documentation Settings
            StaticHelpers::getAppSetting('KMP.AppSettings.HelpUrl', 'https://github.com/Ansteorra/KMP/wiki/App-Settings', null, true);  // Settings help URL

            // Branch Type Configuration - Organizational structure definitions
            // Uses YAML format for complex data structures
            StaticHelpers::getAppSetting('Branches.Types', yaml_emit([
                'Kingdom',          // Top-level organization
                'Principality',     // Major subdivision of kingdom
                'Region',           // Geographic grouping of local groups
                'Local Group',      // Individual chapters (Barony, Shire, etc.)
                'N/A',             // Special case for non-geographic roles
            ]), 'yaml', true);
        }
    }

    /**
     * Middleware Stack Configuration
     *
     * This method configures the HTTP middleware stack that processes every request.
     * The middleware stack follows a specific order where each layer can modify
     * the request/response or halt processing entirely.
     *
     * Middleware Stack Order and Purpose:
     *
     * 1. **ErrorHandlerMiddleware**: Catches exceptions and converts them to
     *    appropriate HTTP error responses. Must be first to catch all errors.
     *
     * 2. **Security Headers Middleware**: Adds comprehensive security headers
     *    including CSP, HSTS, XSS protection, and frame options. This is a
     *    custom inline middleware that implements defense-in-depth security.
     *
     * 3. **AssetMiddleware**: Serves static assets (CSS, JS, images) with
     *    proper caching headers. Handles plugin and theme assets.
     *
     * 4. **RoutingMiddleware**: Matches URLs to controllers and actions.
     *    Populates route parameters in the request object.
     *
     * 5. **BodyParserMiddleware**: Parses request bodies (JSON, XML, form data)
     *    and makes them available via $request->getData().
     *
     * 6. **CsrfProtectionMiddleware**: Validates CSRF tokens to prevent
     *    cross-site request forgery attacks. Uses secure cookie settings.
     *
     * 7. **AuthenticationMiddleware**: Handles user authentication using
     *    session and form-based authentication.
     *
     * 8. **AuthorizationMiddleware**: Checks user permissions and enforces
     *    access control policies. Requires authorization check on all requests.
     *
     * 9. **FootprintMiddleware**: Tracks user activity for auditing purposes.
     *
     * Security Configuration Details:
     * - CSP headers prevent XSS attacks by restricting resource loading
     * - HSTS enforces HTTPS connections
     * - CSRF tokens use secure, HTTP-only, same-site cookies
     * - Authorization requires checks on all requests with redirect handling
     *
     * Performance Considerations:
     * - Asset middleware includes caching configuration
     * - Route caching can be enabled for large applications
     * - Authorization checks are required but cached for performance
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to configure
     * @return \Cake\Http\MiddlewareQueue The configured middleware queue
     * @throws \InvalidArgumentException If middleware configuration is invalid
     * @see \Cake\Error\Middleware\ErrorHandlerMiddleware Error handling
     * @see \Cake\Http\Middleware\CsrfProtectionMiddleware CSRF protection
     * @see \Authentication\Middleware\AuthenticationMiddleware Authentication
     * @see \Authorization\Middleware\AuthorizationMiddleware Authorization
     */
    public function middleware(
        MiddlewareQueue $middlewareQueue,
    ): MiddlewareQueue {
        $middlewareQueue
            // 1. Error Handler - Must be first to catch all exceptions
            // Converts exceptions to appropriate HTTP error responses
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // 2. Security Headers - Comprehensive security header implementation
            // Provides defense-in-depth against common web vulnerabilities
            ->add(function ($request, $handler) {
                $response = $handler->handle($request);
                $isDevelopment = Configure::read('debug');

                // Base security headers (always applied)
                $response = $response
                    // Prevent MIME type sniffing attacks
                    ->withHeader('X-Content-Type-Options', 'nosniff')
                    // Prevent clickjacking by restricting frame embedding
                    ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                    // Enable XSS protection in older browsers
                    ->withHeader('X-XSS-Protection', '1; mode=block')
                    // Control referrer information leakage
                    ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

                // HTTPS enforcement headers (only in production/UAT)
                if (!$isDevelopment) {
                    $response = $response
                        // Enforce HTTPS connections (24 hours cache)
                        ->withHeader('Strict-Transport-Security', 'max-age=86400; includeSubDomains');
                }

                // Build CSP policy
                $csp = "default-src 'self'; " .                                              // Default to same origin
                    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://maps.googleapis.com; " .  // Allow CDN scripts, Leaflet (unpkg), and Google Maps
                    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.googleapis.com; " . // Allow Google Fonts and Leaflet CSS
                    "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net;" .    // Font sources
                    "img-src 'self' data: https:; " .                                // Allow HTTPS images and data URIs
                    "connect-src 'self' https://maps.googleapis.com https://places.googleapis.com https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org; " .             // AJAX/fetch restrictions - allow Google Maps, Places API, and OpenStreetMap tiles
                    "frame-src 'self' https://www.google.com; " .                    // iframe restrictions - allow Google Maps embeds
                    "object-src 'none'; " .                                          // Disable plugins
                    "base-uri 'self'; " .                                            // Prevent base tag attacks
                    "form-action 'self'; " .                                         // Form submission restrictions
                    "frame-ancestors 'self'";                                        // Embedding restrictions

                // Add upgrade-insecure-requests only in production/UAT
                if (!$isDevelopment) {
                    $csp .= "; upgrade-insecure-requests";                           // Auto-upgrade HTTP to HTTPS
                }

                // Comprehensive Content Security Policy
                // Prevents XSS by controlling resource loading sources
                return $response->withHeader('Content-Security-Policy', $csp);
            })

            // 3. Asset Middleware - Static file serving with caching
            // Handles CSS, JS, images, and other static assets
            ->add(
                new AssetMiddleware([
                    'cacheTime' => Configure::read('Asset.cacheTime'),  // Use configured cache time
                ]),
            )

            // 4. Routing Middleware - URL to controller/action mapping
            // For large applications, consider enabling route caching in production
            // See: https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

            // 5. Body Parser Middleware - Request body parsing
            // Parses JSON, XML, and form data into $request->getData()
            // Documentation: https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())

            // 6. CSRF Protection Middleware - Cross-site request forgery protection
            // Uses secure cookie settings for maximum security
            // Skip CSRF for API routes (they use Bearer token authentication)
            // Documentation: https://book.cakephp.org/4/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            ->add(
                (new CsrfProtectionMiddleware([
                    'httponly' => true,    // Prevent JavaScript access to CSRF cookie
                    'secure' => !Configure::read('debug'),      // Only send cookie over HTTPS in production/UAT (Safari requires this to be false for HTTP)
                    'sameSite' => Configure::read('debug') ? 'Lax' : 'Strict', // Lax in dev for Safari compatibility, Strict in production
                ]))->skipCheckCallback(function ($request) {
                    // Skip CSRF for API routes (Bearer token provides security)
                    $path = $request->getUri()->getPath();
                    return str_starts_with($path, '/api/');
                }),
            )

            // 7. Authentication Middleware - User login and session management
            // Must be added after routing and body parser to access request data
            ->add(new AuthenticationMiddleware($this))

            // 8. Authorization Middleware - Permission checking and access control
            // Enforces policy-based authorization on all requests
            ->add(
                new AuthorizationMiddleware($this, [
                    // Identity decorator integrates authorization into user identity
                    'identityDecorator' => function (AuthorizationServiceInterface $auth, KmpIdentityInterface $user) {
                        return $user->setAuthorization($auth);
                    },
                    // Require authorization check on all requests (security best practice)
                    'requireAuthorizationCheck' => true,
                    // Handle unauthorized access attempts
                    'unauthorizedHandler' => [
                        'className' => 'Authorization.Redirect',     // Redirect instead of throwing exception
                        'url' => '/pages/unauthorized',              // Unauthorized access page
                        'queryParam' => 'redirectUrl',               // Store original URL for post-login redirect
                        'exceptions' => [                            // Exception types that trigger redirect
                            MissingIdentityException::class,         // User not logged in
                            ForbiddenException::class,               // User lacks permission
                        ],
                    ],
                ]),
            )

            // 9. Footprint Middleware - User activity tracking for auditing
            // Tracks which user performed what actions for security and compliance
            ->add('Muffin/Footprint.Footprint');

        return $middlewareQueue;
    }

    /**
     * Dependency Injection Container Service Registration
     *
     * This method configures the application's dependency injection container
     * by registering core services and their dependencies. The container
     * manages object lifecycle and handles dependency resolution automatically.
     *
     * Registered Services:
     *
     * 1. **ActiveWindowManagerInterface**: Manages date-bounded entities
     *    that have specific validity periods (like warrants, authorizations).
     *    - Implementation: DefaultActiveWindowManager
     *    - Purpose: Handles entity activation/deactivation based on date ranges
     *    - Usage: Warrant management, officer terms, event registrations
     *
     * 2. **WarrantManagerInterface**: Handles warrant lifecycle management
     *    including creation, approval, expiration, and renewal processes.
     *    - Implementation: DefaultWarrantManager  
     *    - Dependencies: ActiveWindowManagerInterface (for date management)
     *    - Purpose: Officer warrant processing and workflow management
     *    - Usage: Officer appointments, warrant approvals, roster management
     *
     * 3. **CsvExportService**: Provides standardized CSV export functionality
     *    for various data types with consistent formatting and security.
     *    - No dependencies
     *    - Purpose: Data export for reports and bulk operations
     *    - Usage: Member lists, warrant reports, activity data
     *
     * Container Benefits:
     * - Automatic dependency resolution
     * - Singleton instance management
     * - Interface-based programming support
     * - Testability through dependency injection
     * - Service lifecycle management
     *
     * Service Resolution Example:
     * ```php
     * // Container automatically resolves dependencies
     * $warrantManager = $container->get(WarrantManagerInterface::class);
     * // ActiveWindowManagerInterface is automatically injected
     * ```
     *
     * @param \Cake\Core\ContainerInterface $container The DI container to configure
     * @return void
     * @throws \Cake\Core\Exception\CakeException If service registration fails
     * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface Date-bounded entity management
     * @see \App\Services\WarrantManager\WarrantManagerInterface Warrant lifecycle management  
     * @see \App\Services\CsvExportService CSV export functionality
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html CakePHP Dependency Injection
     */
    public function services(ContainerInterface $container): void
    {
        // Register ActiveWindowManager for date-bounded entity management
        // This service handles entities that have validity periods (start/end dates)
        $container->add(
            ActiveWindowManagerInterface::class,   // Interface for dependency injection
            DefaultActiveWindowManager::class,     // Concrete implementation
        );

        // Register WarrantManager for warrant lifecycle management
        // Depends on ActiveWindowManager for handling warrant validity periods
        $container->add(
            WarrantManagerInterface::class,        // Interface for dependency injection
            DefaultWarrantManager::class,          // Concrete implementation
        )->addArgument(ActiveWindowManagerInterface::class);  // Inject ActiveWindowManager dependency

        // Register CSV export service for data export functionality
        // No dependencies required - provides standalone export capabilities
        $container->add(
            CsvExportService::class,               // Concrete class (no interface needed)
        );
        $container->add(
            ICalendarService::class,               // Concrete class (no interface needed)
        );
        $container->add(
            ImpersonationService::class,
        );
    }

    /**
     * Authentication Service Configuration
     *
     * This method configures the authentication service that handles user
     * login, session management, and credential validation. KMP uses a
     * multi-layered authentication approach with session persistence
     * and form-based login with brute force protection.
     *
     * Authentication Flow:
     * 1. Check for existing valid session
     * 2. If no session, attempt form-based authentication
     * 3. Validate credentials against database with password hashing
     * 4. Apply brute force protection to prevent password attacks
     * 5. Redirect unauthenticated users to login page
     *
     * Authenticators (in order of precedence):
     * 1. **Session Authenticator**: Checks for existing user session
     *    - Fastest authentication method
     *    - Validates session tokens and user identity
     *    - Handles session persistence across requests
     *
     * 2. **Form Authenticator**: Processes login form submissions
     *    - Validates username/password from POST data
     *    - Uses email address as username field
     *    - Redirects to login page when credentials are invalid
     *
     * Identifier Configuration:
     * - **KMPBruteForcePassword**: Custom identifier with attack protection
     * - **ORM Resolver**: Validates against Members table in database
     * - **Fallback Password Hasher**: Supports multiple password formats
     *   - Default: Modern bcrypt hashing (preferred)
     *   - Legacy: MD5 hashing (for existing passwords, migrated on login)
     *
     * Security Features:
     * - Brute force protection prevents password guessing attacks
     * - Password migration from legacy MD5 to secure bcrypt
     * - Session-based authentication with secure cookie settings
     * - Automatic redirect to login page for unauthenticated requests
     *
     * Configuration Options:
     * - Login URL: /members/login (form submission target)
     * - Redirect URL: Preserved in query parameter for post-login navigation
     * - Username Field: email_address (unique identifier)
     * - Password Field: password (hashed storage)
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Current HTTP request
     * @return \Authentication\AuthenticationServiceInterface Configured authentication service
     * @throws \Authentication\Exception\AuthenticationException If authentication setup fails
     * @see \Authentication\Identifier\AbstractIdentifier Base identifier interface
     * @see \Authentication\Authenticator\SessionAuthenticator Session handling
     * @see \Authentication\Authenticator\FormAuthenticator Form processing
     * @see \Authentication\PasswordHasher\FallbackPasswordHasher Password migration
     */
    public function getAuthenticationService(
        ServerRequestInterface $request,
    ): AuthenticationServiceInterface {
        $service = new AuthenticationService();
        
        // Check if this is an API request
        $path = $request->getUri()->getPath();
        $isApiRequest = str_starts_with($path, '/api/');

        if ($isApiRequest) {
            // API Authentication: Bearer token only, no redirects
            $service->setConfig([
                'unauthenticatedRedirect' => null,
                'queryParam' => null,
            ]);

            // Load ServicePrincipal authenticator for API routes
            $service->loadAuthenticator('ServicePrincipal', [
                'header' => 'Authorization',
                'tokenPrefix' => 'Bearer',
                'apiKeyHeader' => 'X-API-Key',
                'apiKeyQueryParam' => 'api_key',
            ]);

            // No identifier needed - authenticator handles the full lookup
            return $service;
        }

        // Web Authentication: Session-based with form fallback
        // Configure authentication service behavior
        // Defines where users are redirected when authentication is required
        $service->setConfig([
            'unauthenticatedRedirect' => Router::url([        // Redirect target for unauthenticated users
                'prefix' => false,                            // No route prefix
                'plugin' => null,                             // Core application (no plugin)
                'controller' => 'Members',                    // MembersController
                'action' => 'login',                          // login action
            ]),
            'queryParam' => 'redirect',                       // Query parameter for post-login redirect URL
        ]);

        // Define credential field mapping for database lookup
        // Maps form field names to database column names
        $fields = [
            AbstractIdentifier::CREDENTIAL_USERNAME => 'email_address',  // Use email as username
            AbstractIdentifier::CREDENTIAL_PASSWORD => 'password',       // Password field name
        ];

        // Load authenticators in order of precedence
        // Session authenticator should always be first for performance
        $service->loadAuthenticator('Authentication.Session');          // Check existing sessions first

        // Mobile Card Token authenticator for PWA mobile card access
        // Allows passwordless authentication via secure token in URL
        $service->loadAuthenticator('MobileCardToken', [
            'tokenParam' => 'token',                         // URL parameter name
            'fields' => [
                'mobile_card_token' => 'token'               // Database field mapping
            ],
            'userModel' => 'Members',                        // Members table
        ]);

        // Form authenticator handles login form submissions
        $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,                             // Field mapping configuration
            'loginUrl' => Router::url([                      // Form submission target URL
                'prefix' => false,                           // No route prefix
                'plugin' => null,                            // Core application
                'controller' => 'Members',                   // MembersController
                'action' => 'login',                         // login action
            ]),
        ]);

        // Load custom identifier with brute force protection
        // This replaces the standard password identifier with enhanced security
        $service->loadIdentifier('KMPBruteForcePassword', [
            'resolver' => [
                'className' => 'Authentication.Orm',         // Use ORM for database lookups
                'userModel' => 'Members',                    // Members table for user data
            ],
            'fields' => $fields,                             // Field mapping for credentials

            // Password hasher configuration with fallback support
            // Allows migration from legacy password formats to modern hashing
            'passwordHasher' => [
                'className' => 'Authentication.Fallback',    // Fallback hasher for migration
                'hashers' => [
                    'Authentication.Default',                // Modern bcrypt hashing (preferred)
                    [
                        'className' => 'Authentication.Legacy',  // Legacy password support
                        'hashType' => 'md5',                     // Old MD5 hashing (deprecated)
                        'salt' => false,                         // No salt for legacy MD5
                    ],
                ],
            ],
        ]);

        return $service;
    }

    /**
     * Authorization Service Configuration
     *
     * This method configures the authorization service that handles permission
     * checking and access control throughout the application. KMP uses a
     * policy-based authorization system with multiple resolvers to handle
     * different types of authorization scenarios.
     *
     * Authorization Architecture:
     * KMP implements a multi-layered authorization system that checks permissions
     * at both the ORM (entity) level and controller level. This provides
     * fine-grained access control for data operations and coarse-grained
     * control for application functionality.
     *
     * Policy Resolvers (in order of precedence):
     *
     * 1. **ORM Resolver**: Handles entity-level authorization
     *    - Resolves policies for CakePHP entities and tables
     *    - Checks permissions for CRUD operations (create, read, update, delete)
     *    - Handles data scoping (users only see data they're authorized for)
     *    - Example: MemberPolicy::canView(), BranchPolicy::canEdit()
     *
     * 2. **Controller Resolver**: Handles controller-level authorization  
     *    - Resolves policies for controller actions
     *    - Provides application-level access control
     *    - Handles complex business logic authorization
     *    - Example: ReportsControllerPolicy::canGenerateReports()
     *
     * Policy Resolution Process:
     * 1. Authorization request is made (e.g., $this->Authorization->can('view', $member))
     * 2. ORM resolver attempts to find and execute appropriate entity policy
     * 3. If no entity policy found, controller resolver handles the request
     * 4. Policy method is executed with user context and resource
     * 5. Boolean result determines access (true = allowed, false = denied)
     *
     * Authorization Features:
     * - Entity-level scoping (filter data based on user permissions)
     * - Controller action authorization (restrict access to functionality)
     * - Resource-based permissions (different permissions per entity instance)
     * - Role-based access control integration
     * - Custom policy logic for complex business rules
     *
     * Security Benefits:
     * - Defense in depth through multiple authorization layers
     * - Consistent permission checking across the application
     * - Centralized authorization logic in policy classes
     * - Automatic authorization requirement enforcement
     * - Audit trail through authorization service logging
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Current HTTP request
     * @return \Authorization\AuthorizationServiceInterface Configured authorization service
     * @throws \Authorization\Exception\AuthorizationException If authorization setup fails
     * @see \Authorization\Policy\OrmResolver Entity-level authorization
     * @see \App\Policy\ControllerResolver Controller-level authorization
     * @see \App\Services\AuthorizationService Custom authorization service
     * @see \Authorization\Policy\ResolverCollection Multiple resolver support
     */
    public function getAuthorizationService(
        ServerRequestInterface $request,
    ): AuthorizationServiceInterface {
        // Create resolver collection with multiple authorization strategies
        // Order matters: ORM resolver is checked first, then controller resolver
        $lastResortResolver = new ControllerResolver();     // Controller-level authorization (fallback)
        $ormResolver = new OrmResolver();                   // Entity-level authorization (primary)
        $resolver = new ResolverCollection([$ormResolver, $lastResortResolver]);

        // Return custom authorization service with enhanced functionality
        // KmpAuthorizationService extends the base service with KMP-specific features
        return new KmpAuthorizationService($resolver);
    }
}
