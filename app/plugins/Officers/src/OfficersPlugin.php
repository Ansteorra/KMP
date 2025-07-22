<?php

declare(strict_types=1);

namespace Officers;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use Officers\Event\CallForCellsHandler;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use Officers\Services\OfficersNavigationProvider;
use Officers\Services\OfficersViewCellProvider;
use Officers\Services\DefaultOfficerManager;
use Officers\Services\OfficerManagerInterface;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;

/**
 * Officers Plugin - Comprehensive officer assignment management and hierarchical organization
 *
 * The Officers plugin provides a complete system for managing organizational hierarchy,
 * officer assignments, and warrant integration within the KMP application. This plugin
 * handles the complex workflows of officer appointment, warrant requirements, temporal
 * assignment management, and organizational structure maintenance.
 *
 * ## Key Features
 * - **Hierarchical Organization**: Departments, offices, and reporting structures
 * - **Officer Assignment Management**: Complete assignment lifecycle with warrant integration
 * - **Temporal Management**: ActiveWindow integration for time-based assignments
 * - **Warrant Integration**: Automatic role assignment based on warrant requirements
 * - **Service-Oriented Architecture**: Dependency injection with OfficerManagerInterface
 * - **Navigation Integration**: Dynamic navigation with assignment badges
 * - **View Cell Integration**: Dashboard widgets for member and branch officer displays
 *
 * ## Architecture
 * The plugin follows KMP's service-oriented architecture with:
 * - Controllers for CRUD operations and administrative interfaces
 * - Services for business logic and workflow management
 * - Policies for RBAC authorization and security
 * - View cells for dashboard integration
 * - Frontend controllers for interactive workflows
 *
 * ## Configuration Management
 * The plugin uses versioned configuration management to ensure settings are
 * properly initialized and updated when the plugin version changes.
 *
 * @see OfficerManagerInterface For officer assignment business logic
 * @see OfficersNavigationProvider For navigation integration
 * @see OfficersViewCellProvider For dashboard widget integration
 */
class OfficersPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * Plugin migration order for dependency management
     * 
     * @var int
     */
    protected int $_migrationOrder = 0;

    /**
     * Get the migration order for this plugin
     *
     * The migration order determines when this plugin's migrations are run
     * relative to other plugins, ensuring proper dependency resolution.
     *
     * @return int The migration order (0 = run first)
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * Plugin constructor with migration order configuration
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
     * Load all the plugin configuration and bootstrap logic.
     *
     * This method initializes the Officers plugin by:
     * - Registering navigation items with dynamic badge support
     * - Registering view cells for dashboard integration
     * - Managing configuration versioning and automatic updates
     * - Setting up plugin activation status
     *
     * ## Navigation Registration
     * Registers dynamic navigation items through OfficersNavigationProvider,
     * providing permission-based visibility and real-time assignment badges.
     *
     * ## View Cell Registration
     * Registers dashboard view cells through OfficersViewCellProvider for:
     * - Member officer assignment displays
     * - Branch officer roster widgets
     * - Required officer compliance tracking
     *
     * ## Configuration Versioning
     * Implements versioned configuration management to ensure settings
     * are properly initialized when plugin versions change, including:
     * - Plugin activation status
     * - Status check scheduling
     * - Version tracking
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // Register navigation items instead of using event handlers
        NavigationRegistry::register(
            'Officers',
            [], // Static items (none for Officers)
            function ($user, $params) {
                return OfficersNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cells with the ViewCellRegistry
        ViewCellRegistry::register(
            'Officers',
            [], // Static cells (none for Officers)
            function ($urlParams, $user) {
                return OfficersViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        $currentConfigVersion = "25.01.11.a"; // update this each time you change the config

        $configVersion = StaticHelpers::getAppSetting("Officer.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("Officer.configVersion", $currentConfigVersion, null, true);
            StaticHelpers::getAppSetting("Officer.NextStatusCheck", DateTime::now()->subDays(1)->toDateString(), null, true);
            StaticHelpers::getAppSetting("Plugin.Officers.Active", "yes", null, true);
        }
    }

    /**
     * Add routes for the plugin.
     *
     * Configures routing for the Officers plugin under the `/officers` path prefix.
     * This includes all controller actions for:
     * - Department management (/officers/departments)
     * - Office management (/officers/offices) 
     * - Officer assignment management (/officers/officers)
     * - Roster and reporting (/officers/rosters, /officers/reports)
     *
     * Uses fallback routing to automatically map controller actions following
     * CakePHP conventions while maintaining the plugin namespace.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Officers',
            ['path' => '/officers'],
            function (RouteBuilder $builder) {
                // Add custom routes here

                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Add middleware for the plugin.
     *
     * Currently no custom middleware is required for the Officers plugin.
     * All security and authorization is handled through CakePHP's built-in
     * Authentication and Authorization components configured in controllers.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue The unchanged middleware queue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add your middlewares here

        return $middlewareQueue;
    }

    /**
     * Add commands for the plugin.
     *
     * Currently no custom console commands are provided by the Officers plugin.
     * All officer management operations are handled through the web interface
     * and service layer methods.
     *
     * Future commands might include:
     * - Bulk officer assignment operations
     * - Warrant validation and cleanup
     * - Assignment status reporting
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection The command collection with parent commands
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add your commands here

        $commands = parent::console($commands);

        return $commands;
    }

    /**
     * Register application container services.
     *
     * Registers the core Officers plugin services with the dependency injection container:
     *
     * ## OfficerManagerInterface Service
     * Registers DefaultOfficerManager as the implementation for OfficerManagerInterface,
     * which provides the core business logic for:
     * - Officer assignment workflows
     * - Warrant validation and integration
     * - Temporal assignment management
     * - Release and transition processing
     *
     * ## Service Dependencies
     * The DefaultOfficerManager requires:
     * - ActiveWindowManagerInterface: For temporal assignment validation
     * - WarrantManagerInterface: For warrant requirement checking and role assignment
     *
     * This registration enables dependency injection throughout the application,
     * allowing controllers and other services to use the OfficerManagerInterface
     * without direct coupling to the implementation.
     *
     * ## Usage Example
     * ```php
     * // In a controller or service
     * public function __construct(OfficerManagerInterface $officerManager) {
     *     $this->officerManager = $officerManager;
     * }
     * ```
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
        // Add your services here
        $container->add(
            OfficerManagerInterface::class,
            DefaultOfficerManager::class,
        )
            ->addArgument(ActiveWindowManagerInterface::class)
            ->addArgument(WarrantManagerInterface::class);
    }
}

/**
 * ## Officers Plugin Usage Examples
 *
 * ### Plugin Integration
 * The Officers plugin is automatically loaded by KMP's plugin system:
 * 
 * ```php
 * // In config/plugins.php
 * 'Officers' => [
 *     'migrationOrder' => 0,
 * ]
 * ```
 *
 * ### Service Usage
 * Access the officer manager service through dependency injection:
 *
 * ```php
 * use Officers\Services\OfficerManagerInterface;
 * 
 * class MyController extends AppController {
 *     public function __construct(OfficerManagerInterface $officerManager) {
 *         $this->officerManager = $officerManager;
 *     }
 * 
 *     public function assignOfficer() {
 *         $success = $this->officerManager->assign($memberId, $officeId, $startDate, $endDate);
 *     }
 * }
 * ```
 *
 * ### Navigation Integration
 * Navigation items are automatically provided with permission-based visibility:
 *
 * ```php
 * // Navigation items include:
 * // - Officers (main section with assignment count badge)
 * // - Assign Officer (workflow action)
 * // - Manage Departments (administrative)
 * // - Manage Offices (administrative) 
 * // - Officer Rosters (reporting)
 * ```
 *
 * ### View Cell Integration
 * Dashboard widgets are automatically available:
 *
 * ```php
 * // In templates
 * echo $this->cell('Officers.MemberOfficers', ['member' => $member]);
 * echo $this->cell('Officers.BranchOfficers', ['branch' => $branch]);
 * echo $this->cell('Officers.BranchRequiredOfficers', ['branch' => $branch]);
 * ```
 *
 * ### Configuration Management
 * Plugin settings are automatically managed:
 *
 * ```php
 * // Settings managed automatically:
 * // - Officer.configVersion: Plugin version tracking
 * // - Officer.NextStatusCheck: Status validation scheduling
 * // - Plugin.Officers.Active: Plugin activation status
 * ```
 */
