<?php

declare(strict_types=1);

namespace Waivers;

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
use Waivers\Services\WaiversNavigationProvider;
use Waivers\Services\WaiversViewCellProvider;
use App\KMP\StaticHelpers;

/**
 * Waivers Plugin - Gathering Waiver Tracking System
 *
 * Provides comprehensive waiver management for gatherings, including waiver
 * templates, upload tracking, compliance monitoring, and decline/rejection workflows.
 *
 * @see /docs/5.7-waivers-plugin.md
 */
class WaiversPlugin extends BasePlugin implements KMPPluginInterface
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
     * The Template plugin uses default order (0) as it has no special dependencies.
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
            'waivers',                     // Source identifier for this plugin
            [],                            // Static navigation items (none in this case)
            function ($user, $params) {    // Dynamic callback for navigation generation
                return WaiversNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cells for dashboard integration
        // View cells provide reusable components that can appear on various pages
        ViewCellRegistry::register(
            'Waivers',
            [], // Static cells (none - all dynamic)
            function ($urlParams, $user) {
                return WaiversViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        // Initialize plugin configuration settings
        // This ensures default settings are available on first run
        $this->initializeSettings();
    }

    /**
     * Configure Plugin Routes
     *
     * Defines the URL routes for this plugin. All plugin routes are automatically
     * prefixed with the plugin path (e.g., /template/...).
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
            'Waivers',
            ['path' => '/waivers'],
            function (RouteBuilder $builder) {
                // Custom routes can be added here
                // Example: $builder->connect('/custom-path', ['controller' => 'WaiverTypes', 'action' => 'index']);

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
        // $container->add(TemplateServiceInterface::class, DefaultTemplateService::class);
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
        // Example: $commands->add('template:import', ImportCommand::class);

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
        $currentConfigVersion = '1.0.1';
        $configVersion = StaticHelpers::getAppSetting('Waivers.configVersion', '0.0.0', null, true);

        if ($configVersion != $currentConfigVersion) {
            // Update configuration version
            StaticHelpers::setAppSetting('Waivers.configVersion', $currentConfigVersion, null, true);

            // Initialize default settings
            StaticHelpers::getAppSetting('Plugin.Waivers.Active', 'yes', null, true);
            StaticHelpers::getAppSetting('Plugin.Waivers.ShowInNavigation', 'yes', null, true);
            StaticHelpers::getAppSetting('Plugin.Waivers.HelloWorldMessage', 'Hello, World!', null, true);

            // Compliance settings
            StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', null, true);
        }
    }
}
