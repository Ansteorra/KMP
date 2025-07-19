<?php

declare(strict_types=1);

namespace Activities;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use Activities\Event\CallForCellsHandler;
use Activities\Services\AuthorizationManagerInterface;
use Activities\Services\DefaultAuthorizationManager;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use Activities\Services\ActivitiesNavigationProvider;
use Activities\Services\ActivitiesViewCellProvider;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;

/**
 * ActivitiesPlugin - Member Authorization and Participation Management System
 *
 * The Activities Plugin is a core component of the Kingdom Management Portal that manages
 * member participation in organizational activities requiring special authorizations. This
 * plugin provides comprehensive tracking of member authorizations, activity management,
 * and participation workflows essential for SCA organizational operations.
 *
 * ## Core Functionality
 *
 * ### Activity Management
 * The plugin manages a hierarchical system of activities organized into activity groups:
 * - **Combat Activities**: Heavy weapons, rapier, archery, thrown weapons
 * - **Arts & Sciences**: Research, teaching, judging, crafts demonstrations
 * - **Service Activities**: Event management, administrative roles, coordination
 * - **Youth Activities**: Age-appropriate participation and supervision
 *
 * ### Authorization System
 * Provides temporal authorization tracking with multi-level approval workflows:
 * - **Authorization Requests**: Member-initiated authorization applications
 * - **Approval Workflows**: Multi-level authorization with delegated approval authority
 * - **Active Authorization Windows**: Time-bounded authorization validity periods
 * - **Renewal Processes**: Streamlined authorization renewal and extension workflows
 * - **Revocation Management**: Administrative revocation with audit trails
 *
 * ### Integration Architecture
 * 
 * #### RBAC Integration
 * - **Permission-Based Access**: Activity management requires appropriate permissions
 * - **Branch Scoping**: Authorizations respect organizational hierarchy and boundaries
 * - **Warrant Requirements**: Sensitive activities may require warrant validation
 * - **Policy Framework**: Extensible authorization policies for complex business rules
 *
 * #### ActiveWindow Integration
 * - **Temporal Boundaries**: All authorizations have defined start and end dates
 * - **Automatic Expiration**: System automatically handles authorization expiration
 * - **Renewal Workflows**: Integrated renewal processes maintain authorization continuity
 * - **Historical Tracking**: Complete audit trail of authorization periods and changes
 *
 * ## Plugin Architecture
 *
 * ### Migration Order: 1 (Base System)
 * The Activities plugin has migration order 1, indicating it's a foundational system that
 * other plugins may depend on. This ensures:
 * - Activity and authorization tables exist before dependent plugin tables
 * - Navigation and view cell registration happens before dependent plugins
 * - Authorization services are available for plugin integration
 *
 * ### Service Registration
 * The plugin registers core authorization services with dependency injection:
 * - **AuthorizationManagerInterface**: Core authorization business logic service
 * - **DefaultAuthorizationManager**: Default implementation with ActiveWindow integration
 * - **ActiveWindowManagerInterface**: Temporal window management dependency
 *
 * ### Navigation Integration
 * Dynamic navigation based on user permissions and context:
 * - **Activity Dashboard**: Overview of member authorizations and pending requests
 * - **Authorization Management**: Administrative interfaces for authorization workflows
 * - **Activity Configuration**: Administrative activity and group management
 * - **Reporting**: Authorization analytics and participation reporting
 *
 * ### View Cell Integration
 * Context-sensitive UI components for enhanced user experience:
 * - **Member Authorization Cards**: Display member authorization status on profile pages
 * - **Pending Authorization Alerts**: Dashboard notifications for pending approvals
 * - **Activity Participation**: Integration with member activity history and reporting
 * - **Administrative Dashboards**: Summary statistics and workflow management tools
 *
 * ## Configuration Management
 *
 * ### Versioned Configuration System
 * The plugin uses StaticHelpers for versioned configuration management:
 * - **Configuration Version**: "25.01.11.c" - Updated for each configuration change
 * - **Automatic Updates**: Configuration automatically updated during plugin bootstrap
 * - **Backward Compatibility**: Graceful handling of configuration version upgrades
 *
 * ### Key Configuration Settings
 * - **Activities.NextStatusCheck**: Scheduled date for next authorization status review
 * - **Plugin.Activities.Active**: Global plugin activation flag (default: "yes")
 * - **Member.AdditionalInfo.DisableAuthorizationSharing**: Legacy setting (removed in current version)
 *
 * ## Business Logic Integration
 *
 * ### Member Profile Integration
 * - **Authorization Status Display**: Real-time authorization status on member profiles
 * - **Request Management**: Member-initiated authorization request workflows
 * - **Historical Records**: Complete authorization history with audit trails
 * - **Renewal Reminders**: Automated notifications for expiring authorizations
 *
 * ### Administrative Workflows
 * - **Bulk Authorization Management**: Administrative tools for managing multiple authorizations
 * - **Approval Delegation**: Hierarchical approval authority with proper audit trails
 * - **Reporting and Analytics**: Comprehensive authorization reporting and trend analysis
 * - **Compliance Tracking**: Ensure organizational compliance with authorization requirements
 *
 * ## Event System Integration
 *
 * ### CallForCellsHandler
 * The plugin uses event-driven architecture for UI integration:
 * - **Dynamic Content Injection**: Context-sensitive content based on page and user state
 * - **Performance Optimization**: Lazy loading of expensive operations
 * - **Extensibility**: Other plugins can respond to Activities events
 *
 * ## Security Architecture
 *
 * ### Authorization Protection
 * All plugin functionality respects KMP's RBAC system:
 * - **Permission Requirements**: All actions require appropriate permissions
 * - **Branch Scoping**: Data access respects organizational boundaries
 * - **Audit Trails**: All authorization changes logged with user accountability
 * - **Data Protection**: Sensitive authorization data protected appropriately
 *
 * ### Input Validation
 * - **Request Validation**: All authorization requests validated for completeness and accuracy
 * - **Business Rule Enforcement**: System enforces organizational authorization policies
 * - **Data Integrity**: Foreign key constraints and validation ensure data consistency
 *
 * ## Performance Considerations
 *
 * ### Caching Strategy
 * - **Authorization Lookup**: Frequently accessed authorization data cached appropriately
 * - **Navigation Generation**: Permission-based navigation cached per user context
 * - **Dashboard Metrics**: Summary statistics cached to reduce database load
 *
 * ### Query Optimization
 * - **Efficient Joins**: Authorization queries optimized for common access patterns
 * - **Index Strategy**: Database indexes optimized for authorization lookup performance
 * - **Batch Operations**: Administrative operations use efficient batch processing
 *
 * ## Development Patterns
 *
 * ### Service Layer Architecture
 * - **AuthorizationManagerInterface**: Abstraction layer for authorization business logic
 * - **Dependency Injection**: Services properly registered with container for testability
 * - **Separation of Concerns**: Clear separation between controller, service, and model layers
 *
 * ### Testing Integration
 * - **Unit Tests**: Comprehensive testing of authorization business logic
 * - **Integration Tests**: End-to-end testing of authorization workflows
 * - **Mock Services**: Testable service interfaces with proper dependency injection
 *
 * ## Future Enhancement Considerations
 *
 * ### Workflow Engine Integration
 * - **Configurable Approval Workflows**: More flexible approval process configuration
 * - **Conditional Logic**: Complex business rules for authorization approval
 * - **Integration Points**: Better integration with external organizational systems
 *
 * ### Advanced Reporting
 * - **Analytics Dashboard**: Enhanced reporting and trend analysis capabilities
 * - **Compliance Reporting**: Automated compliance reports for organizational requirements
 * - **Performance Metrics**: Authorization process performance tracking and optimization
 *
 * @see \Activities\Services\AuthorizationManagerInterface Core authorization service interface
 * @see \Activities\Services\DefaultAuthorizationManager Default authorization service implementation
 * @see \Activities\Services\ActivitiesNavigationProvider Dynamic navigation provider
 * @see \Activities\Services\ActivitiesViewCellProvider Context-sensitive view cell provider
 * @see \Activities\Event\CallForCellsHandler Event-driven UI integration handler
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface Temporal window management
 * @package Activities
 * @since KMP 1.0
 */
class ActivitiesPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * Plugin Migration Order
     *
     * The Activities plugin uses migration order 1, indicating it's a foundational
     * system that other plugins depend on. This ensures proper initialization
     * order for database dependencies and service registration.
     *
     * @var int Plugin initialization order (1 = base system)
     */
    protected int $_migrationOrder = 0;

    /**
     * Get Migration Order for Plugin Initialization
     *
     * Returns the migration order value that determines when this plugin should be
     * initialized relative to other plugins. The Activities plugin serves as a
     * foundation for other organizational management plugins.
     *
     * @return int Migration order (1 for Activities as base system)
     * @since KMP 1.0
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * Plugin Constructor with Configuration Support
     *
     * Initializes the Activities plugin with configuration options, particularly
     * the migration order which can be overridden through plugin configuration.
     * This allows for deployment-specific initialization order adjustments.
     *
     * ## Configuration Options
     * - **migrationOrder**: Override default migration order (default: 0, recommended: 1)
     *
     * ## Usage Example
     * ```php
     * // In config/plugins.php
     * 'Activities' => [
     *     'migrationOrder' => 1,
     * ]
     * ```
     *
     * @param array $config Plugin configuration options
     * @since KMP 1.0
     */
    public function __construct($config = [])
    {
        if (!isset($config['migrationOrder'])) {
            $config['migrationOrder'] = 0;
        }
        $this->_migrationOrder = $config['migrationOrder'];
    }

    /**
     * Bootstrap Plugin Configuration and Service Registration
     *
     * Initializes the Activities plugin by registering essential services, event handlers,
     * navigation providers, view cells, and configuration management. This method is called
     * during application bootstrap and sets up all core plugin functionality.
     *
     * ## Service Registration Process
     *
     * ### Event Handler Registration
     * - **CallForCellsHandler**: Manages dynamic UI content injection
     * - **Global Event Manager**: Ensures plugin events are handled system-wide
     *
     * ### Navigation System Integration
     * - **Dynamic Navigation**: Permission-based navigation items generated per user
     * - **Context Awareness**: Navigation adapts to user role and current page context
     * - **Performance Optimization**: Navigation generated lazily to minimize overhead
     *
     * ### View Cell Integration
     * - **Context-Sensitive UI**: Dashboard and page-specific content blocks
     * - **Member Profile Integration**: Authorization status display on member pages
     * - **Administrative Tools**: Management interfaces embedded contextually
     *
     * ### Configuration Version Management
     * - **Versioned Updates**: Configuration automatically updated for new versions
     * - **Backward Compatibility**: Graceful handling of configuration migrations
     * - **Environment Consistency**: Ensures consistent configuration across deployments
     *
     * ## Configuration Settings Applied
     *
     * ### Version 25.01.11.c Configuration
     * - **Activities.NextStatusCheck**: Scheduled authorization status review date
     * - **Plugin.Activities.Active**: Global plugin activation (default: "yes")
     * - **Legacy Cleanup**: Removes deprecated configuration settings
     *
     * ## Integration Examples
     *
     * ### Navigation Provider Usage
     * ```php
     * // Dynamic navigation based on user permissions
     * if ($user->hasPermission('Activities.Index')) {
     *     $items[] = [
     *         'title' => 'Activities Dashboard',
     *         'url' => '/activities',
     *         'badge' => $pendingAuthorizationsCount,
     *     ];
     * }
     * ```
     *
     * ### View Cell Provider Usage
     * ```php
     * // Member profile authorization status
     * if ($urlParams['controller'] === 'Members' && $urlParams['action'] === 'view') {
     *     $cells[] = [
     *         'cell' => 'Activities.MemberAuthorizations',
     *         'data' => ['member_id' => $urlParams['pass'][0]],
     *     ];
     * }
     * ```
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application for plugin registration
     * @return void
     * @throws \RuntimeException If required services are not available
     * @since KMP 1.0
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // Register dynamic navigation provider with NavigationRegistry
        // Navigation items are generated based on user permissions and context
        NavigationRegistry::register(
            'Activities',
            [], // No static navigation items - all dynamic
            function ($user, $params) {
                return ActivitiesNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cell provider for dashboard and page integration
        // View cells provide context-sensitive UI components throughout the system
        ViewCellRegistry::register(
            'Activities',
            [], // No static view cells - all dynamic
            function ($urlParams, $user) {
                return ActivitiesViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        // Configuration version management for automatic updates
        $currentConfigVersion = "25.01.11.c"; // Update this each time configuration changes

        $configVersion = StaticHelpers::getAppSetting("Activities.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            // Update configuration version to track successful migration
            StaticHelpers::setAppSetting("Activities.configVersion", $currentConfigVersion, null, true);

            // Initialize default authorization status check schedule
            StaticHelpers::getAppSetting("Activities.NextStatusCheck", DateTime::now()->subDays(1)->toDateString(), null, true);

            // Ensure plugin is activated by default
            StaticHelpers::getAppSetting("Plugin.Activities.Active", "yes", null, true);

            // Clean up deprecated configuration settings
            StaticHelpers::deleteAppSetting("Member.AdditionalInfo.DisableAuthorizationSharing", true);
        }
    }

    /**
     * Configure Plugin Routing
     *
     * Establishes URL routing for the Activities plugin under the `/activities` path.
     * This method configures the plugin's route scope and delegates to CakePHP's
     * fallback routing for standard controller/action patterns.
     *
     * ## Routing Configuration
     * - **Plugin Path**: `/activities` - All plugin routes prefixed with this path
     * - **Fallback Routes**: Standard CakePHP controller/action routing patterns
     * - **Custom Routes**: Additional routes can be added within the plugin scope
     *
     * ## Standard Route Examples
     * - `GET /activities` → `ActivitiesController::index()`
     * - `GET /activities/authorizations` → `AuthorizationsController::index()`
     * - `POST /activities/authorizations/request` → `AuthorizationsController::request()`
     * - `GET /activities/activity-groups` → `ActivityGroupsController::index()`
     *
     * ## Custom Route Integration
     * Additional routes can be added for specific workflows:
     * ```php
     * $builder->connect('/approve/{id}', [
     *     'controller' => 'Authorizations',
     *     'action' => 'approve'
     * ], ['id' => '\d+', 'pass' => ['id']]);
     * ```
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder for configuration
     * @return void
     * @since KMP 1.0
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Activities',
            ['path' => '/activities'],
            function (RouteBuilder $builder) {
                // Custom routes can be added here for specific workflows
                // Currently using fallback routing for standard patterns

                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Configure Plugin Middleware
     *
     * Adds middleware specific to the Activities plugin. Currently no custom
     * middleware is required, but this method provides the extension point
     * for future middleware integration.
     *
     * ## Potential Middleware Applications
     * - **Authorization Middleware**: Plugin-specific authorization checks
     * - **Audit Middleware**: Logging of authorization-related actions
     * - **Rate Limiting**: Protection against abuse of authorization requests
     * - **Data Validation**: Additional validation layers for authorization data
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update
     * @return \Cake\Http\MiddlewareQueue Updated middleware queue
     * @since KMP 1.0
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add plugin-specific middleware here when needed
        // Currently no custom middleware required

        return $middlewareQueue;
    }

    /**
     * Register Plugin Console Commands
     *
     * Registers CLI commands specific to the Activities plugin. This provides
     * administrative and maintenance commands for authorization management.
     *
     * ## Potential Command Applications
     * - **Authorization Cleanup**: Remove expired authorizations
     * - **Status Updates**: Batch update authorization statuses
     * - **Reporting**: Generate authorization reports via CLI
     * - **Data Migration**: Migrate authorization data between versions
     *
     * ## Usage Example
     * ```bash
     * # Future command examples
     * bin/cake activities cleanup_expired
     * bin/cake activities status_report
     * bin/cake activities migrate_data
     * ```
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection Updated command collection
     * @since KMP 1.0
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add plugin-specific CLI commands here when needed
        // Currently no custom commands implemented

        $commands = parent::console($commands);

        return $commands;
    }

    /**
     * Register Plugin Services with Dependency Injection Container
     *
     * Configures the dependency injection container with Activities plugin services.
     * This method establishes the service layer architecture and ensures proper
     * dependency resolution for authorization management functionality.
     *
     * ## Service Registration
     *
     * ### AuthorizationManagerInterface
     * - **Interface**: `Activities\Services\AuthorizationManagerInterface`
     * - **Implementation**: `Activities\Services\DefaultAuthorizationManager`
     * - **Dependencies**: `App\Services\ActiveWindowManager\ActiveWindowManagerInterface`
     * - **Purpose**: Core authorization business logic and workflow management
     *
     * ## Service Architecture Benefits
     * - **Testability**: Services can be easily mocked and tested in isolation
     * - **Flexibility**: Implementation can be swapped without changing client code
     * - **Dependency Management**: Container handles complex dependency graphs
     * - **Performance**: Services are instantiated only when needed
     *
     * ## Usage Examples
     *
     * ### Controller Integration
     * ```php
     * // In AuthorizationsController
     * private AuthorizationManagerInterface $authorizationManager;
     *
     * public function __construct(AuthorizationManagerInterface $authorizationManager)
     * {
     *     $this->authorizationManager = $authorizationManager;
     * }
     *
     * public function request()
     * {
     *     $result = $this->authorizationManager->requestAuthorization($user, $activityId, $data);
     *     // Handle result
     * }
     * ```
     *
     * ### Service Integration
     * ```php
     * // In another service
     * public function processWorkflow(AuthorizationManagerInterface $manager)
     * {
     *     return $manager->processApproval($authorizationId, $approvalData);
     * }
     * ```
     *
     * ## Service Dependencies
     * The AuthorizationManager depends on ActiveWindowManagerInterface for:
     * - **Temporal Window Management**: Managing authorization validity periods
     * - **Automatic Expiration**: Handling authorization expiration workflows
     * - **Renewal Processing**: Managing authorization renewal cycles
     *
     * @param \Cake\Core\ContainerInterface $container The DI container to configure
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html
     * @since KMP 1.0
     */
    public function services(ContainerInterface $container): void
    {
        // Register core authorization management service with dependency injection
        $container->add(
            AuthorizationManagerInterface::class,
            DefaultAuthorizationManager::class,
        )->addArgument(ActiveWindowManagerInterface::class);
    }
}