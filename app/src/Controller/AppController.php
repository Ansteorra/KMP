<?php

declare(strict_types=1);

/**
 * Kingdom Management Portal (KMP) - Application Controller
 * 
 * This is the base controller for the KMP application, providing shared functionality
 * across all controllers in the system. It handles critical application-wide concerns
 * including request detection, navigation history, plugin validation, view cell management,
 * and Turbo/AJAX request handling.
 * 
 * ## Core Responsibilities
 * 
 * ### 1. Request Type Detection & Processing
 * - Detects CSV export requests via URL inspection (.csv extension)
 * - Handles AJAX, JSON, XML, and Turbo Frame requests
 * - Configures appropriate layouts and response formats
 * 
 * ### 2. Plugin Validation & Security
 * - Validates that requested plugins are enabled before processing
 * - Redirects to safe locations when disabled plugins are accessed
 * - Integrates with StaticHelpers for plugin status checking
 * 
 * ### 3. Navigation History Management
 * - Maintains a session-based page stack for navigation breadcrumbs
 * - Intelligently handles back navigation and prevents duplicate entries
 * - Excludes AJAX, POST requests, and specific controllers from history
 * 
 * ### 4. View Cell Orchestration
 * - Integrates with ViewCellRegistry for dynamic UI components
 * - Provides plugin view cells based on current context and user permissions
 * - Supports event-driven view data enhancement
 * 
 * ### 5. Turbo Integration
 * - Detects Turbo Frame requests and configures appropriate layouts
 * - Provides frame ID context for partial page updates
 * - Maintains session state across Turbo navigations
 * 
 * ## Architecture Integration
 * 
 * This controller integrates with several KMP subsystems:
 * - Authentication/Authorization: Via CakePHP plugins
 * - Plugin System: Via StaticHelpers and event system
 * - View System: Via ViewCellRegistry and event dispatching
 * - Session Management: For navigation history and flash messages
 * - Request Processing: For format detection and routing
 * 
 * ## Usage Patterns
 * 
 * All application controllers should extend this class to inherit:
 * ```php
 * class MembersController extends AppController
 * {
 *     // Inherits CSV detection, plugin validation, navigation history, etc.
 * }
 * ```
 * 
 * ## Security Considerations
 * 
 * - Plugin access validation prevents unauthorized plugin usage
 * - Navigation history excludes sensitive operations (logout, login)
 * - Request type detection helps prevent CSRF attacks
 * - Session management includes proper cleanup on logout
 * 
 * @package App\Controller
 * @author KMP Development Team
 * @since KMP 1.0
 * @see \App\Services\ViewCellRegistry For view cell management
 * @see \App\KMP\StaticHelpers For plugin utilities
 * @see \Cake\Controller\Controller For base controller functionality
 */

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Services\ViewCellRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;

class AppController extends Controller
{
    /**
     * Event constant for view cell registration
     * 
     * This event is triggered during beforeFilter to allow plugins to register
     * view cells that should be displayed on specific pages. Plugins listen for
     * this event and can return view cell configurations.
     * 
     * @var string
     */
    public const VIEW_PLUGIN_EVENT = 'KMP.plugins.callForViewCells';

    /**
     * Event constant for view data enhancement
     * 
     * This event is triggered at the end of beforeFilter to allow plugins to
     * add additional view data based on the current URL parameters. This enables
     * plugins to inject context-specific data into views.
     * 
     * @var string
     */
    public const VIEW_DATA_EVENT = 'KMP.plugins.callForViewData';

    /**
     * Plugin view cells collection
     * 
     * Contains view cells provided by plugins for the current request context.
     * These are fetched from the ViewCellRegistry based on URL parameters and
     * user permissions, then made available to all views.
     * 
     * Structure: Array of view cell configurations with properties like:
     * - type: Cell placement (e.g., 'sidebar', 'header', 'footer')
     * - order: Display order within the placement
     * - cell: Cell class name and parameters
     * 
     * @var array
     */
    protected array $pluginViewCells = [];

    /**
     * CSV request detection flag
     * 
     * Indicates if the current request is for CSV output, determined by checking
     * if the URL contains a .csv extension. This flag is used throughout the
     * application to modify response formatting and view rendering.
     * 
     * When true:
     * - Views may render data tables as CSV
     * - Headers are set for file download
     * - Layouts may be simplified or omitted
     * 
     * @var bool
     */
    protected bool $isCsvRequest = false;

    /**
     * CSV request detection accessor
     * 
     * Returns whether the current request is intended for CSV output. This is
     * determined by checking if the request URL contains a .csv extension.
     * 
     * This method is used by:
     * - Controllers to determine response format
     * - Views to conditionally render CSV-appropriate content
     * - Services to modify data processing for export
     * 
     * @return bool True if the request is for CSV output, false otherwise
     * 
     * @example
     * ```php
     * if ($this->isCsvRequest()) {
     *     $this->response = $this->response->withType('csv');
     *     $this->viewBuilder()->setLayout('csv');
     * }
     * ```
     */
    public function isCsvRequest(): bool
    {
        return $this->isCsvRequest;
    }

    /**
     * Pre-action filter hook for application-wide processing
     * 
     * This method runs before every controller action and handles critical
     * application-wide functionality. It processes requests in several phases:
     * 
     * ## Phase 1: Request Type Detection
     * - Registers custom request detectors (CSV)
     * - Sets global flags for request types
     * - Configures view variables for template access
     * 
     * ## Phase 2: Plugin Validation & Security
     * - Validates that requested plugins are enabled
     * - Redirects unauthorized plugin access to safe locations
     * - Preserves user context during redirects
     * 
     * ## Phase 3: Navigation History Management
     * - Maintains session-based page stack for breadcrumbs
     * - Handles special cases (login, logout, AJAX requests)
     * - Prevents duplicate entries and manages back navigation
     * 
     * ## Phase 4: Context Preparation
     * - Extracts URL parameters for downstream processing
     * - Prepares record ID and model information
     * - Sets up view variables for template access
     * 
     * ## Phase 5: View Cell Integration
     * - Fetches plugin view cells based on context and permissions
     * - Organizes cells by type and display order
     * - Makes cells available to view layer
     * 
     * ## Phase 6: Turbo Integration
     * - Detects Turbo Frame requests
     * - Configures appropriate layouts for partial updates
     * - Sets frame context variables
     * 
     * ## Phase 7: Event System Integration
     * - Dispatches view data event for plugin enhancement
     * - Allows plugins to inject additional context data
     * 
     * @param EventInterface $event The beforeFilter event
     * @return void
     * 
     * @throws \Cake\Http\Exception\ForbiddenException When plugin is disabled
     * 
     * @example Plugin Access Control
     * ```php
     * // URL: /activities/events/index
     * // If Activities plugin is disabled, user is redirected to:
     * // - /members/view/{userId} (if logged in)
     * // - /members/login (if anonymous)
     * ```
     * 
     * @example Navigation History
     * ```php
     * // Session contains:
     * // pageStack = ['/members', '/members/view/1', '/branches/index']
     * // Available in templates as $pageStack for breadcrumbs
     * ```
     * 
     * @example Turbo Frame Detection
     * ```php
     * // Request header: Turbo-Frame: member-details
     * // Results in:
     * // - Layout set to 'turbo_frame'
     * // - $isTurboFrame = true
     * // - $turboFrameId = 'member-details'
     * ```
     */

    public function beforeFilter(EventInterface $event)
    {
        // === PHASE 1: REQUEST TYPE DETECTION ===

        // Register custom CSV request detector
        // This checks if the URL contains .csv extension for export functionality
        $this->request->addDetector(
            'csv',
            function ($request) {
                // Check the URL for .csv extension (e.g., /members/index.csv)
                return strpos($request->getRequestTarget(), '.csv') !== false;
            },
        );

        // Set global CSV request flag and make available to views
        $this->isCsvRequest = $this->request->is('csv');
        $this->set('isCsvRequest', $this->isCsvRequest);

        // === PHASE 2: PLUGIN VALIDATION & SECURITY ===

        // Check if the request is for a plugin and validate it's enabled
        $plugin = $this->request->getParam('plugin');
        if ($plugin != null) {
            // Use StaticHelpers to check plugin status from configuration
            if (StaticHelpers::pluginEnabled($plugin) == false) {
                // Plugin is disabled - show error and redirect to safe location
                $this->Flash->error("The plugin $plugin is not enabled.");
                $currentUser = $this->request->getAttribute('identity');
                if ($currentUser != null) {
                    // Logged in users go to their profile
                    $this->redirect(['plugin' => null, 'controller' => 'Members', 'action' => 'view', $currentUser->id]);
                } else {
                    // Anonymous users go to login
                    $this->redirect(['plugin' => null, 'controller' => 'Members', 'action' => 'login']);
                }
            }
        }

        // Call parent beforeFilter to ensure proper inheritance chain
        parent::beforeFilter($event);

        // === PHASE 3: URL CONTEXT PREPARATION ===

        // Extract URL parameters for processing and view access
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            $this->request->getParam('pass'), // Additional URL parameters
        ];

        // Build complete current URL including base path if configured
        $baseSub = Configure::read('App.base');
        $currentUrl = $this->request->getRequestTarget();
        if ($baseSub != null) {
            // Prepend base path for subdirectory installations
            $currentUrl = $baseSub . $currentUrl;
        }
        $this->set('currentUrl', $currentUrl);

        // === PHASE 4: NAVIGATION HISTORY MANAGEMENT ===

        $session = $this->getRequest()->getSession();
        $isNoStack = false;

        // Handle special cases that should not be included in navigation history
        if ($params['controller'] == 'Members') {
            if ($params['action'] == 'logout') {
                // Logout should not be in history and destroys session
                $isNoStack = true;
                $session->destroy();
            }
            if ($params['action'] == 'login') {
                // Login should not be in history but preserves flash messages
                $isNoStack = true;
                $config = $this->getRequest()->getFlash()->getConfig();
                // Preserve flash messages across session destruction
                $flash = $session->read('Flash.' . $config['key']);
                $session->destroy();
                $session->write('Flash.' . $config['key'], $flash);
            }
        }
        if ($params['controller'] == 'NavBar') {
            // Navigation bar requests should not affect history
            $isNoStack = true;
        }

        // Get current page stack from session
        $pageStack = $session->read('pageStack', []);
        if ($params['action'] == 'index') {
            // Index pages reset the navigation stack
            $pageStack = [];
        }

        // Determine if this request should be excluded from history
        $isAjax = $this->request->is('ajax') || $this->request->is('json') || $this->request->is('xml') || $this->request->is('csv');
        $turboRequest = $this->request->getHeader('Turbo-Frame') != null;
        $isAjax = $isAjax || $turboRequest;
        if (!$isNoStack) {
            // Allow manual exclusion via query parameter
            $isNoStack = $this->request->getQuery('nostack') != null;
        }
        $isPostType = $this->request->is('post') || $this->request->is('put') || $this->request->is('delete');

        // Add to navigation history if appropriate
        if (!$isAjax && !$isPostType && !$isNoStack) {
            if (empty($pageStack)) {
                // Initialize stack with current URL
                $pageStack[] = $currentUrl;
            }
            $historyCount = count($pageStack);

            // Handle back navigation - remove duplicate when going back
            if (($historyCount > 1) && ($pageStack[$historyCount - 2] == $currentUrl)) {
                $historyCount--;
                array_pop($pageStack);
            }

            // Add current URL if it's different from the last entry
            if ($pageStack[$historyCount - 1] != $currentUrl) {
                $pageStack[] = $currentUrl;
                $historyCount++;
            }
        }

        // Save updated page stack and make available to views
        $session->write('pageStack', $pageStack);
        $this->set('pageStack', $pageStack);

        // === PHASE 5: VIEW CELL INTEGRATION ===

        // Prepare URL parameters for view cell registry lookup
        $urlParams = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            'pass' => $this->request->getParam('pass') ?? [],
            'query' => $this->request->getQueryParams(),
        ];

        // Get current user for permission checking
        $currentUser = $this->request->getAttribute('identity');

        // Fetch view cells from registry based on context and user permissions
        // This replaces the older event-based system for better performance
        $this->pluginViewCells = ViewCellRegistry::getViewCells($urlParams, $currentUser);
        $this->set('pluginViewCells', $this->pluginViewCells);

        // === PHASE 6: TURBO INTEGRATION ===

        // Check for Turbo Frame requests and configure appropriate response
        if ($this->request->getHeader('Turbo-Frame')) {
            // Use minimal layout for partial page updates
            $this->viewBuilder()->setLayout('turbo_frame');
            $this->set('isTurboFrame', true);
            $this->set('turboFrameId', $this->request->getHeader('Turbo-Frame')[0]);
        } else {
            // Standard full page request
            $this->set('isTurboFrame', false);
        }

        // === PHASE 7: CONTEXT VARIABLE SETUP ===

        // Make current user available to all views
        $this->set('user', $this->request->getAttribute('identity'));

        // Extract and prepare record ID from URL parameters
        $recordId = $this->request->getParam('pass');
        if (is_array($recordId) && count($recordId) > 0) {
            // Use first parameter as primary record ID
            $recordId = $recordId[0];
        } elseif (is_array($recordId) && count($recordId) == 0) {
            // No parameters - use -1 as default
            $recordId = -1;
        } elseif (is_array($recordId)) {
            // Multiple parameters - concatenate for display
            foreach ($recordId as $key => $value) {
                $recordId .= $value . ', ';
            }
        }
        $this->set('recordId', $recordId);

        // Prepare model name with plugin prefix if applicable
        $recordModel = $params['controller'];
        if ($params['plugin'] != null) {
            $recordModel = $params['plugin'] . '.' . $recordModel;
        }
        $this->set('recordModel', $recordModel);

        // === PHASE 8: EVENT SYSTEM INTEGRATION ===

        // Dispatch event to allow plugins to add view data
        $event = new Event(static::VIEW_DATA_EVENT, $this, ['url' => $params]);
        EventManager::instance()->dispatch($event);
    }

    /**
     * Controller initialization hook
     * 
     * This method is called during controller construction and is responsible for
     * loading shared components that all controllers in the application need.
     * It runs after the controller is constructed but before any action is called.
     * 
     * ## Loaded Components
     * 
     * ### Authentication Component
     * - Handles user authentication via the CakePHP Authentication plugin
     * - Manages login/logout functionality and user identity
     * - Integrates with custom identity interface (KmpIdentityInterface)
     * 
     * ### Authorization Component  
     * - Handles user authorization via the CakePHP Authorization plugin
     * - Manages permission checking through policy classes
     * - Integrates with KMP's role-based access control system
     * 
     * ### Flash Component
     * - Provides flash messaging functionality for user feedback
     * - Displays success, error, warning, and info messages
     * - Messages persist across redirects via session storage
     * 
     * ## Optional Components
     * 
     * ### FormProtection Component (Commented)
     * - Provides CSRF protection for forms
     * - Currently disabled but available for enhanced security
     * - Can be enabled by uncommenting the loadComponent line
     * 
     * ## Extension Points
     * 
     * Child controllers can override this method to load additional components:
     * ```php
     * public function initialize(): void
     * {
     *     parent::initialize(); // Always call parent first
     *     $this->loadComponent('Paginator');
     *     $this->loadComponent('RequestHandler');
     * }
     * ```
     * 
     * @return void
     * 
     * @see \App\KMP\KmpIdentityInterface For custom identity requirements
     * @see \App\Policy\* For authorization policy classes
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load authentication component for user login/logout functionality
        $this->loadComponent('Authentication.Authentication');

        // Load authorization component for permission checking
        $this->loadComponent('Authorization.Authorization');

        // Load flash messaging component for user feedback
        $this->loadComponent('Flash');

        // Note: Dependency injection container access for services
        // $this->appSettings = ServiceProvider::getContainer()->get(AppSettingsService::class);

        /*
         * Form Protection Component (Optional)
         * 
         * Enable the following component for recommended CakePHP form protection settings.
         * This provides CSRF protection and other security features for forms.
         * 
         * @see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        // $this->loadComponent('FormProtection');
    }

    /**
     * Organize view cells by type and display order
     * 
     * This protected method organizes view cells returned from plugins into a
     * structured array grouped by placement type and sorted by display order.
     * This allows templates to easily render cells in the correct locations
     * and order.
     * 
     * ## Input Format
     * ```php
     * $viewCells = [
     *     ['type' => 'sidebar', 'order' => 2, 'cell' => 'Awards.RecentAwards'],
     *     ['type' => 'sidebar', 'order' => 1, 'cell' => 'Activities.UpcomingEvents'],
     *     ['type' => 'header', 'order' => 1, 'cell' => 'Notifications.Alerts'],
     * ];
     * ```
     * 
     * ## Output Format
     * ```php
     * $organized = [
     *     'sidebar' => [
     *         1 => ['type' => 'sidebar', 'order' => 1, 'cell' => 'Activities.UpcomingEvents'],
     *         2 => ['type' => 'sidebar', 'order' => 2, 'cell' => 'Awards.RecentAwards'],
     *     ],
     *     'header' => [
     *         1 => ['type' => 'header', 'order' => 1, 'cell' => 'Notifications.Alerts'],
     *     ],
     * ];
     * ```
     * 
     * ## Usage in Templates
     * ```php
     * // Render all sidebar cells in order
     * foreach ($organizedCells['sidebar'] ?? [] as $cell) {
     *     echo $this->cell($cell['cell'], $cell['params'] ?? []);
     * }
     * ```
     * 
     * @param array $viewCells Flat array of view cell configurations
     * @return array Organized array grouped by type and sorted by order
     * 
     * @deprecated This method is currently unused as view cells are organized
     *             in ViewCellRegistry. Kept for backward compatibility.
     */
    protected function organizeViewCells($viewCells)
    {
        $cells = [];
        foreach ($viewCells as $cell) {
            // Group cells by their placement type (sidebar, header, footer, etc.)
            $cells[$cell['type']][$cell['order']] = $cell;
        }

        // Sort each placement type by display order
        foreach ($cells as $key => $value) {
            ksort($cells[$key]);
        }

        return $cells;
    }

    /**
     * Authorize the current URL/action
     * 
     * This protected method performs authorization checking for the current
     * request using the CakePHP Authorization component. It builds the
     * authorization context from URL parameters and checks if the current
     * user has permission to access the requested resource.
     * 
     * ## Authorization Context
     * 
     * The method builds an authorization context containing:
     * - controller: The target controller name
     * - action: The target action name  
     * - plugin: The plugin name (if any)
     * - prefix: The routing prefix (if any)
     * - pass: Additional URL parameters
     * 
     * ## Policy Integration
     * 
     * This method works with KMP's policy system:
     * - Controller policies check action-level permissions
     * - Resource policies check entity-level permissions
     * - Role-based access control via permission system
     * 
     * ## Usage Pattern
     * 
     * This method is typically called from controller actions that need
     * explicit authorization checking beyond the automatic checks:
     * 
     * ```php
     * public function sensitiveAction()
     * {
     *     $this->authorizeCurrentUrl(); // Explicit authorization check
     *     
     *     // Proceed with action logic
     * }
     * ```
     * 
     * ## Error Handling
     * 
     * If authorization fails, the Authorization component will:
     * - Throw ForbiddenException for unauthorized access
     * - Log the authorization failure
     * - Redirect to appropriate error page
     * 
     * @return void
     * @throws \Authorization\Exception\ForbiddenException When authorization fails
     * 
     * @see \App\Policy\* For policy implementation classes
     * @see \Cake\Controller\Component\AuthorizationComponent For component details
     */
    protected function authorizeCurrentUrl()
    {
        // Build authorization context from current request parameters
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            $this->request->getParam('pass'), // Additional URL parameters
        ];

        // Perform authorization check via Authorization component
        $this->Authorization->authorize($params);
    }

    /**
     * Switch View Mode Action
     * 
     * Allows users to switch between mobile and desktop view modes by storing
     * their preference in the session and redirecting to the appropriate interface.
     * 
     * ## View Modes & Redirects
     * 
     * - mobile: Redirects to viewMobileCard with user's mobile_card_token
     * - desktop: Redirects to Members/profile page
     * 
     * ## Session Storage
     * 
     * The preference is stored in the session as 'viewMode' and persists across
     * the user's session until they log out or switch modes again.
     * 
     * ## URL Parameters
     * 
     * - mode: The view mode to switch to ('mobile' or 'desktop')
     * 
     * ## Usage
     * 
     * ```php
     * // Switch to mobile view
     * $this->Html->link('Switch to Mobile', [
     *     'controller' => 'App',
     *     'action' => 'switchView',
     *     '?' => ['mode' => 'mobile']
     * ]);
     * 
     * // Switch to desktop view
     * $this->Html->link('Switch to Desktop', [
     *     'controller' => 'App',
     *     'action' => 'switchView',
     *     '?' => ['mode' => 'desktop']
     * ]);
     * ```
     * 
     * @return \Cake\Http\Response Redirects to mobile card or member profile
     */
    public function switchView()
    {
        // Skip authorization check - view switching should be available to all authenticated users
        $this->Authorization->skipAuthorization();

        $mode = $this->request->getQuery('mode', 'desktop');

        // Validate mode parameter
        if (!in_array($mode, ['mobile', 'desktop'])) {
            $mode = 'desktop';
        }

        // Store preference in session
        $session = $this->request->getSession();
        $session->write('viewMode', $mode);

        // Get current user identity
        $currentUser = $this->request->getAttribute('identity');

        $this->Flash->success(__('Switched to {0} view.', $mode));

        // Redirect based on mode
        if ($mode === 'mobile') {
            // Redirect to mobile card view with user's token
            if ($currentUser && $currentUser->mobile_card_token) {
                return $this->redirect([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                    $currentUser->mobile_card_token
                ]);
            } else {
                // Fallback if no mobile card token exists
                $this->Flash->error(__('Mobile card not available. Please contact an administrator.'));
                return $this->redirect(['controller' => 'Members', 'action' => 'index', 'plugin' => null]);
            }
        } else {
            // Redirect to desktop profile view
            return $this->redirect([
                'controller' => 'Members',
                'action' => 'profile',
                'plugin' => null
            ]);
        }
    }
}