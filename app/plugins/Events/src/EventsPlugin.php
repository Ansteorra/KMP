<?php

declare(strict_types=1);

namespace Events;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use Events\Services\EventsNavigationProvider;
use App\KMP\StaticHelpers;

/**
 * Events Plugin - Complete Boilerplate for KMP Plugin Development
 *
 * This plugin serves as a comprehensive Events and example for creating new plugins
 * within the Kingdom Management Portal (KMP) system. It demonstrates all common plugin
 * components, patterns, and integration points.
 *
 * ## Purpose
 *
 * The Events plugin provides:
 * - **Reference Implementation**: Working examples of all plugin components
 * - **Quick Start**: Copy and modify to create new plugins
 * - **Best Practices**: Demonstrates KMP coding standards and patterns
 * - **Complete Integration**: Shows navigation, authorization, and UI integration
 *
 * ## Features Demonstrated
 *
 * ### Core Components
 * - **Controllers**: HelloWorldController with standard CRUD patterns
 * - **Models**: Example Table and Entity classes with proper configuration
 * - **Policies**: HelloWorldPolicy for RBAC authorization
 * - **Services**: Business logic separation and dependency injection
 *
 * ### KMP Integration
 * - **Navigation**: Automatic menu registration with NavigationRegistry
 * - **View Cells**: Dashboard widget integration examples
 * - **Authorization**: Policy-based access control with KMP's RBAC system
 * - **Routing**: Plugin-specific route configuration
 *
 * ### Frontend Components
 * - **Stimulus.js Controllers**: Interactive frontend behavior
 * - **Eventss**: Bootstrap-styled view Eventss
 * - **CSS Styling**: Plugin-specific stylesheet examples
 *
 * ### Database
 * - **Migrations**: Example table creation and schema management
 * - **Seeds**: Sample data for development and testing
 *
 * ## Architecture
 *
 * This plugin follows KMP's service-oriented architecture:
 * - Controllers handle HTTP requests and coordinate responses
 * - Services contain business logic and workflow management
 * - Policies enforce authorization rules
 * - Models manage data access and persistence
 * - View cells provide reusable dashboard components
 *
 * ## Usage as Events
 *
 * To create a new plugin from this Events:
 * 1. Copy the entire `plugins/Events` directory
 * 2. Rename to your plugin name (e.g., `plugins/MyPlugin`)
 * 3. Search and replace "Events" with "MyPlugin" throughout
 * 4. Update composer.json with your plugin details
 * 5. Register in config/plugins.php with appropriate migration order
 * 6. Customize the components for your specific needs
 *
 * @package Events
 * @author KMP Development Team
 * @version 1.0.0
 * @since KMP 25.01.11
 * @see \App\KMP\KMPPluginInterface
 * @see \Cake\Core\BasePlugin
 */
class EventsPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * Plugin migration order for KMP plugin system
     *
     * @var int Migration order priority for database setup
     */
    protected int $_migrationOrder = 0;

    /**
     * Get Migration Order
     *
     * Returns the migration order priority for this plugin, which determines
     * the sequence in which plugin migrations are executed during system setup.
     * The Events plugin uses default order (0) as it has no special dependencies.
     *
     * @return int Migration order priority
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * Constructor
     *
     * Initialize the plugin with migration configuration from plugins.php.
     *
     * @param array $config Plugin configuration including migrationOrder
     */
    public function __construct($config = [])
    {
        if (!isset($config['migrationOrder'])) {
            $config['migrationOrder'] = 0;
        }
        $this->_migrationOrder = $config['migrationOrder'];
    }

    /**
     * Plugin Bootstrap Process
     *
     * This method is called during application bootstrap and handles:
     * - Navigation item registration
     * - View cell registration for dashboard widgets
     * - Event listener attachment
     * - Service initialization
     *
     * The bootstrap process runs early in the application lifecycle, making
     * it ideal for registering services and setting up plugin infrastructure.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application instance
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // Register navigation items using the NavigationRegistry
        // This integrates the plugin into the main application menu system
        NavigationRegistry::register(
            'events',                     // Source identifier for this plugin
            [],                            // Static navigation items (none in this case)
            function ($user, $params) {    // Dynamic callback for navigation generation
                return EventsNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cells for dashboard integration
        // View cells provide reusable components that can appear on various pages
        //ViewCellRegistry::register(
        //    'Events',
        //    [], // Static cells (none for Events)
        //    function ($urlParams, $user) {
        // Dynamic view cells can be added here based on context
        //        return [
        // Example: Add a dashboard cell
        // [
        //     'cell' => 'Events.HelloWorld',
        //     'position' => 'main',
        //     'order' => 100,
        // ],
        //        ];
        //    }
        //);

        // Initialize plugin configuration settings
        // This ensures default settings are available on first run
        $this->initializeSettings();
    }

    /**
     * Configure Plugin Routes
     *
     * Defines the URL routes for this plugin. All plugin routes are automatically
     * prefixed with the plugin path (e.g., /Events/...).
     *
     * The plugin uses fallback routing for standard controller/action patterns,
     * but custom routes can be added for specific workflows.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to configure
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Events',
            ['path' => '/Events'],
            function (RouteBuilder $builder) {
                // Custom routes can be added here
                // Example: $builder->connect('/custom-path', ['controller' => 'HelloWorld', 'action' => 'index']);

                // Use fallback routing for standard controller/action patterns
                $builder->fallbacks();
            }
        );

        parent::routes($routes);
    }

    /**
     * Configure Plugin Middleware
     *
     * Add middleware specific to this plugin. Middleware can handle:
     * - Request preprocessing
     * - Response postprocessing
     * - Authentication checks
     * - Rate limiting
     * - Custom header management
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to modify
     * @return \Cake\Http\MiddlewareQueue The modified middleware queue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add custom middleware here if needed
        // Example: $middlewareQueue->add(new CustomMiddleware());

        return $middlewareQueue;
    }

    /**
     * Configure Plugin Services
     *
     * Register services in the dependency injection container. This is where
     * you bind interfaces to implementations for service classes.
     *
     * Services registered here can be injected into controllers, other services,
     * and commands through constructor injection.
     *
     * @param \Cake\Core\ContainerInterface $container The DI container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        // Register services here
        // Example:
        // $container->add(EventsServiceInterface::class, DefaultEventsService::class);
    }

    /**
     * Configure Plugin Console Commands
     *
     * Register CLI commands that are specific to this plugin. Commands can be
     * used for:
     * - Data migration and import
     * - Batch processing
     * - Maintenance tasks
     * - Report generation
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to modify
     * @return \Cake\Console\CommandCollection The modified command collection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add console commands here if needed
        // Example: $commands->add('Events:import', ImportCommand::class);

        return $commands;
    }

    /**
     * Initialize Plugin Settings
     *
     * Sets up default configuration values in the KMP settings system.
     * This ensures that the plugin has sensible defaults on first installation.
     *
     * Settings are versioned to allow automatic updates when the plugin is upgraded.
     *
     * @return void
     */
    protected function initializeSettings(): void
    {
        // Version-based configuration management
        $currentConfigVersion = '0.0.1';
        $configVersion = StaticHelpers::getAppSetting('Events.configVersion', '0.0.0', null, true);

        if ($configVersion != $currentConfigVersion) {
            // Update configuration version
            StaticHelpers::setAppSetting('Events.configVersion', $currentConfigVersion, null, true);

            // Initialize default settings
            StaticHelpers::getAppSetting('Plugin.Events.Active', 'yes', null, true);
        }
    }
}