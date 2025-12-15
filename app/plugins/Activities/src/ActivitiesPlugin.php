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
 * Core plugin managing member authorization workflows for activity-based participation.
 * Provides authorization lifecycle management, multi-level approval workflows, and
 * temporal access control integrated with KMP's RBAC system.
 *
 * **See comprehensive documentation**: [5.6.1-activities-plugin-architecture.md](../../docs/5.6.1-activities-plugin-architecture.md)
 *
 * @see \Activities\Services\AuthorizationManagerInterface Authorization business logic service
 * @see \Activities\Services\DefaultAuthorizationManager Default authorization implementation
 * @see \Activities\Services\ActivitiesNavigationProvider Dynamic navigation provider
 * @see \Activities\Services\ActivitiesViewCellProvider Context-sensitive UI components
 * @package Activities
 */
class ActivitiesPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * Plugin Migration Order
     *
     * Configurable migration order (default: 0) for controlling initialization
     * sequence relative to other plugins. Set to 1 for base system behavior.
     *
     * @var int Plugin initialization order
     */
    protected int $_migrationOrder = 0;

    /**
     * Get Migration Order for Plugin Initialization
     *
     * Returns the migration order determining when this plugin initializes
     * relative to other plugins.
     *
     * @return int Migration order value
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * Plugin Constructor with Configuration Support
     *
     * Initializes the plugin with configuration options, particularly the
     * migration order which can be overridden through plugin configuration.
     *
     * Configuration Options:
     * - **migrationOrder**: Override default migration order (default: 0)
     *
     * @param array $config Plugin configuration options
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
     * Initializes the plugin by registering services, event handlers, navigation,
     * view cells, and configuration management. Manages configuration versioning
     * with automatic updates and backward compatibility.
     *
     * Configuration Version Management:
     * - Tracks current version to detect migration requirements
     * - Initializes default settings on first run
     * - Cleans up deprecated settings
     * - Ensures consistent configuration across deployments
     *
     * See documentation: [5.6.1-activities-plugin-architecture.md](../../docs/5.6.1-activities-plugin-architecture.md#plugin-initialization)
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
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
     * Uses CakePHP's fallback routing for standard controller/action patterns.
     *
     * Route Examples:
     * - GET /activities → ActivitiesController::index()
     * - GET /activities/authorizations → AuthorizationsController::index()
     * - POST /activities/authorizations/request → AuthorizationsController::request()
     * - GET /activities/activity-groups → ActivityGroupsController::index()
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder for configuration
     * @return void
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
     * Extension point for plugin-specific middleware. Currently no custom middleware
     * is required. Potential applications include authorization checks, audit logging,
     * rate limiting, and additional validation layers.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update
     * @return \Cake\Http\MiddlewareQueue Updated middleware queue
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
     * Extension point for CLI commands. Reserved for future administrative and
     * maintenance commands supporting cleanup, status updates, reporting, and
     * data migrations.
     *
     * Planned Commands:
     * - bin/cake activities cleanup_expired
     * - bin/cake activities status_report
     * - bin/cake activities migrate_data
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection Updated command collection
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
     * Registers AuthorizationManagerInterface with its default implementation.
     * The service handles authorization lifecycle and business logic for all
     * authorization workflows.
     *
     * Service Details:
     * - Interface: Activities\Services\AuthorizationManagerInterface
     * - Implementation: Activities\Services\DefaultAuthorizationManager
     * - Dependency: App\Services\ActiveWindowManager\ActiveWindowManagerInterface
     * - Provides: Authorization request, approval, denial, and revocation workflows
     *
     * See documentation: [5.6.1-activities-plugin-architecture.md](../../docs/5.6.1-activities-plugin-architecture.md#service-registration)
     *
     * @param \Cake\Core\ContainerInterface $container The DI container to configure
     * @return void
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