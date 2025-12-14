<?php

declare(strict_types=1);

namespace GitHubIssueSubmitter;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\StaticHelpers;

/**
 * GitHubIssueSubmitter Plugin - Anonymous Feedback Collection and GitHub Integration
 *
 * Provides anonymous feedback collection through direct GitHub issue creation.
 * Users can submit bug reports, feature requests, and feedback without authentication.
 * 
 * Configuration is managed through StaticHelpers with version-based automatic updates.
 *
 * @package GitHubIssueSubmitter
 * @since 1.0.0
 * @see /docs/5.4-github-issue-submitter-plugin.md Full documentation
 */
class GitHubIssueSubmitterPlugin extends BasePlugin
{
    /**
     * Load plugin configuration and bootstrap logic.
     *
     * Initializes version-based configuration management, ensuring all required
     * settings (GitHub repository, activation status, user messaging) are properly
     * initialized and updated when configuration version changes.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        $currentConfigVersion = "25.01.11.a"; // update this each time you change the config

        $configVersion = StaticHelpers::getAppSetting("GitHubIssueSubmitter.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("GitHubIssueSubmitter.configVersion", $currentConfigVersion, null, true);
            StaticHelpers::getAppSetting("KMP.GitHub.Owner", "Ansteorra", null, true);
            StaticHelpers::getAppSetting("KMP.GitHub.Project", "KMP", null, true);
            StaticHelpers::getAppSetting("Plugin.GitHubIssueSubmitter.Active", "yes", null, true);
            StaticHelpers::getAppSetting("Plugin.GitHubIssueSubmitter.PopupMessage", "This Feedback form is anonymous and will be submitted to the KMP GitHub repository. Please do not include any pii or use this for support requests.", null, true);
        }
    }

    /**
     * Add routes for the plugin.
     *
     * Configures routing with base path `/git-hub-issue-submitter` for feedback
     * submission endpoints. Uses fallback routes for standard CakePHP conventions.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'GitHubIssueSubmitter',
            ['path' => '/git-hub-issue-submitter'],
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
    public function services(ContainerInterface $container): void
    {
        // Add your services here
    }
}
