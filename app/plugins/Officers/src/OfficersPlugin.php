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
use App\KMP\KMPApiPluginInterface;
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
use Officers\Services\Api\ReadOnlyDepartmentServiceInterface;
use Officers\Services\Api\ReadOnlyOfficeServiceInterface;
use Officers\Services\Api\ReadOnlyOfficerRosterServiceInterface;
use Officers\Services\Api\DefaultReadOnlyDepartmentService;
use Officers\Services\Api\DefaultReadOnlyOfficeService;
use Officers\Services\Api\DefaultReadOnlyOfficerRosterService;

/**
 * Officers Plugin - Officer assignment management and hierarchical organization
 *
 * Provides complete officer lifecycle management including hierarchical organization,
 * warrant integration, temporal assignments, and service-oriented architecture.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficersPlugin extends BasePlugin implements KMPPluginInterface, KMPApiPluginInterface
{
    /**
     * @var int Plugin migration order
     */
    protected int $_migrationOrder = 0;

    /**
     * @return int Migration order (0 = run first)
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
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
     * Bootstrap plugin: register navigation, view cells, and manage configuration versioning.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        NavigationRegistry::register(
            'Officers',
            [],
            function ($user, $params) {
                return OfficersNavigationProvider::getNavigationItems($user, $params);
            }
        );

        ViewCellRegistry::register(
            'Officers',
            [],
            function ($urlParams, $user) {
                return OfficersViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        $currentConfigVersion = "25.01.11.a";
        $configVersion = StaticHelpers::getAppSetting("Officer.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("Officer.configVersion", $currentConfigVersion, null, true);
            StaticHelpers::getAppSetting("Officer.NextStatusCheck", DateTime::now()->subDays(1)->toDateString(), null, true);
            StaticHelpers::getAppSetting("Plugin.Officers.Active", "yes", null, true);
        }
    }

    /**
     * Configure plugin routes under /officers path.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Officers',
            ['path' => '/officers'],
            function (RouteBuilder $builder) {
                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * Register API endpoints for the Officers plugin.
     *
     * @param \Cake\Routing\RouteBuilder $builder API scope route builder
     * @return void
     */
    public function registerApiRoutes(RouteBuilder $builder): void
    {
        $builder->connect('/officers/departments', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Departments',
            'action' => 'index',
        ]);
        $builder->connect('/officers/departments/{id}', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Departments',
            'action' => 'view',
        ])->setPatterns(['id' => '[0-9]+'])->setPass(['id']);

        $builder->connect('/officers/offices', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Offices',
            'action' => 'index',
        ]);
        $builder->connect('/officers/offices/{id}', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Offices',
            'action' => 'view',
        ])->setPatterns(['id' => '[0-9]+'])->setPass(['id']);

        $builder->connect('/officers/roster', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Officers',
            'action' => 'index',
        ]);
        $builder->connect('/officers/roster/{id}', [
            'plugin' => 'Officers',
            'prefix' => 'Api/V1',
            'controller' => 'Officers',
            'action' => 'view',
        ])->setPatterns(['id' => '[0-9]+'])->setPass(['id']);
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * @param \Cake\Console\CommandCollection $commands The command collection
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        return $commands;
    }

    /**
     * Register OfficerManagerInterface service with DI container.
     *
     * @param \Cake\Core\ContainerInterface $container The Container
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(
            OfficerManagerInterface::class,
            DefaultOfficerManager::class,
        )
            ->addArgument(ActiveWindowManagerInterface::class)
            ->addArgument(WarrantManagerInterface::class);

        $container->add(
            ReadOnlyDepartmentServiceInterface::class,
            DefaultReadOnlyDepartmentService::class,
        );
        $container->add(
            ReadOnlyOfficeServiceInterface::class,
            DefaultReadOnlyOfficeService::class,
        );
        $container->add(
            ReadOnlyOfficerRosterServiceInterface::class,
            DefaultReadOnlyOfficerRosterService::class,
        );
    }
}
