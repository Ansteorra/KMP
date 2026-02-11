<?php

declare(strict_types=1);

namespace Queue;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\RouteBuilder;
use Queue\Command\AddCommand;
use Queue\Command\BakeQueueTaskCommand;
use Queue\Command\InfoCommand;
use Queue\Command\JobCommand;
use Queue\Command\RunCommand;
use Queue\Command\WorkerCommand;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Services\NavigationRegistry;
use Queue\Services\QueueNavigationProvider;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;

/**
 * Plugin for Queue
 */
class QueuePlugin extends BasePlugin implements KMPPluginInterface
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
	 * @var bool
	 */
	protected bool $middlewareEnabled = false;

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
			'Queue',
			[], // Static items (none for Queue)
			function ($user, $params) {
				return QueueNavigationProvider::getNavigationItems($user, $params);
			}
		);

		$currentConfigVersion = "25.01.29.a"; // update this each time you change the config

		$configVersion = StaticHelpers::getAppSetting("Queue.configVersion", "0.0.0", null, true);
		if ($configVersion != $currentConfigVersion) {
			StaticHelpers::getAppSetting("Plugin.Queue.Active", "yes", null, true);
			StaticHelpers::setAppSetting("Queue.configVersion", $currentConfigVersion);
		}
	}

	/**
	 * Console hook
	 *
	 * @param \Cake\Console\CommandCollection $commands The command collection
	 *
	 * @return \Cake\Console\CommandCollection
	 */
	public function console(CommandCollection $commands): CommandCollection
	{
		$commands->add('queue add', AddCommand::class);
		$commands->add('queue info', InfoCommand::class);
		$commands->add('queue run', RunCommand::class);
		$commands->add('queue worker', WorkerCommand::class);
		$commands->add('queue job', JobCommand::class);
		if (class_exists('Bake\Command\SimpleBakeCommand')) {
			$commands->add('bake queue_task', BakeQueueTaskCommand::class);
		}

		return $commands;
	}

	/**
	 * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
	 *
	 * @return void
	 */
	public function routes(RouteBuilder $routes): void
	{
		$routes->plugin(
			'Queue',
			['path' => '/queue'],
			function (RouteBuilder $builder) {
				// Add custom routes here

				$builder->fallbacks();
			}
		);
		parent::routes($routes);
	}

	/**
	 * @param \Cake\Core\ContainerInterface $container The DI container instance
	 *
	 * @return void
	 */
	public function services(ContainerInterface $container): void
	{
		$container->add(ContainerInterface::class, $container);
		$container
			->add(RunCommand::class)
			->addArgument(ContainerInterface::class);
	}
}
