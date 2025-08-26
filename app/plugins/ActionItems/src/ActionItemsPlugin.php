<?php

declare(strict_types=1);

namespace ActionItems;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use ActionItems\Event\CallForCellsHandler;
use ActionItems\Services\AuthorizationManagerInterface;
use ActionItems\Services\DefaultAuthorizationManager;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use ActionItems\Services\ActionItemsNavigationProvider;
use ActionItems\Services\ActionItemsViewCellProvider;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;

/**
 * Plugin for ActionItems
 */
class ActionItemsPlugin extends BasePlugin implements KMPPluginInterface
{
    protected int $_migrationOrder = 0;
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

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
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // Register navigation items instead of using event handlers
        NavigationRegistry::register(
            'ActionItems',
            [], // Static items (none for ActionItems)
            function ($user, $params) {
                return ActionItemsNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cells with the ViewCellRegistry
        ViewCellRegistry::register(
            'ActionItems',
            [], // Static cells (none for Activities)
            function ($urlParams, $user) {
                return ActionItemsViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        $currentConfigVersion = "01.01.01.a"; // update this each time you change the config

        $configVersion = StaticHelpers::getAppSetting("ActionItems.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("ActionItems.configVersion", $currentConfigVersion, null, true);
            StaticHelpers::getAppSetting("ActionItems.NextStatusCheck", DateTime::now()->subDays(1)->toDateString(), null, true);
            StaticHelpers::getAppSetting("Plugin.ActionItems.Active", "yes", null, true);
            StaticHelpers::deleteAppSetting("Member.AdditionalInfo.DisableAuthorizationSharing", true);
        }
    }

    /**
     * Add routes for the plugin.
     *
     * If your plugin has many routes and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'ActionItems',
            ['path' => '/action-items'],
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
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add your middlewares here

        return $middlewareQueue;
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update.
     * @return \Cake\Console\CommandCollection
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
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void {}
}
