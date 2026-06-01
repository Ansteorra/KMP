<?php

declare(strict_types=1);

namespace Awards;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use App\KMP\KMPPluginInterface;
use Cake\Event\EventManager;
use Awards\Event\CallForCellsHandler;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use Awards\Services\AwardsNavigationProvider;
use Awards\Services\AwardsViewCellProvider;
use Awards\Services\RecommendationFormService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationQueryService;
use Awards\Services\RecommendationStateLogService;
use Awards\Services\RecommendationSubmissionService;
use Awards\Services\RecommendationTransitionService;
use Awards\Services\RecommendationUpdateService;
use Awards\Services\AdHocBestowalService;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalNotificationVarsService;
use Awards\Services\BestowalFormService;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\BestowalQueryService;
use Awards\Services\BestowalRecommendationLinkService;
use Awards\Services\BestowalRecommendationSyncService;
use Awards\Services\BestowalStateLogService;
use Awards\Services\BestowalTransitionService;
use Awards\Services\BestowalUpdateService;
use App\KMP\StaticHelpers;

/**
 * Awards Plugin - Award recommendation management with state machine workflow.
 *
 * @package Awards
 * @see /docs/5.2-awards-plugin.md For complete documentation
 */
class AwardsPlugin extends BasePlugin implements KMPPluginInterface
{
    /** @var int Migration order priority for database setup */
    protected int $_migrationOrder = 0;

    /**
     * @return int Migration order priority
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
     * Initialize navigation, view cells, and version-controlled configuration.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // Register navigation items instead of using event handlers
        NavigationRegistry::register(
            'Awards',
            [], // Static items (none for Awards)
            function ($user, $params) {
                return AwardsNavigationProvider::getNavigationItems($user, $params);
            }
        );

        // Register view cells with ViewCellRegistry
        ViewCellRegistry::register(
            'Awards',
            [], // Static cells (none for Awards)
            function ($urlParams, $user) {
                return AwardsViewCellProvider::getViewCells($urlParams, $user);
            }
        );

        $currentConfigVersion = "26.02.22.a"; // Removed Awards.ViewConfig.* settings (unused)

        $configVersion = StaticHelpers::getAppSetting("Awards.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("Awards.configVersion", $currentConfigVersion, null, true);
        } // end if ($configVersion != $currentConfigVersion)

        // Always ensure default settings exist (idempotent — only creates if missing).
        StaticHelpers::getAppSetting("Awards.RecButtonClass", "btn-warning", null, true);
            StaticHelpers::getAppSetting("Member.AdditionalInfo.CallIntoCourt", "select:Never,With Notice,Without Notice|user|public", null, true);
            StaticHelpers::getAppSetting("Member.AdditionalInfo.CourtAvailability", "select:None,Morning,Evening,Any|user|public", null, true);
            StaticHelpers::getAppSetting("Member.AdditionalInfo.PersonToGiveNoticeTo", "text|user|public", null, true);
            StaticHelpers::getAppSetting("Plugin.Awards.Active", "yes", null, true);
            // Note: RecommendationStatuses, RecommendationStateRules, and
            // RecommendationStatesRequireCanViewHidden are now managed via
            // database tables (awards_recommendation_statuses, awards_recommendation_states,
            // awards_recommendation_state_field_rules) instead of app_settings YAML.
    }

    /**
     * Configure plugin routes with JSON, PDF, and CSV format support.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Awards',
            ['path' => '/awards'],
            function (RouteBuilder $builder) {
                $builder->setExtensions(["json", "pdf", "csv"]);
                $builder->fallbacks();
            }
        );
        parent::routes($routes);
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update
     * @return \Cake\Http\MiddlewareQueue Updated middleware queue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection Updated command collection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        return $commands;
    }

    /**
     * @param \Cake\Core\ContainerInterface $container The container to update
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(RecommendationFormService::class);
        $container->add(RecommendationQueryService::class);
        $container->add(RecommendationSubmissionService::class);
        $container->add(RecommendationUpdateService::class);
        $container->add(RecommendationTransitionService::class);
        $container->add(RecommendationGroupingService::class);
        $container->add(RecommendationStateLogService::class);
        $container->add(BestowalCreationService::class);
        $container->add(BestowalTransitionService::class);
        $container->add(BestowalRecommendationSyncService::class);
        $container->add(BestowalCancellationService::class);
        $container->add(AdHocBestowalService::class);
        $container->add(BestowalStateLogService::class);
        $container->add(BestowalQueryService::class);
        $container->add(BestowalNotificationVarsService::class);
        $container->add(BestowalFormService::class);
        $container->add(BestowalGatheringLookupService::class);
        $container->add(BestowalRecommendationLinkService::class);
        $container->add(BestowalUpdateService::class);

        // Register workflow actions and conditions for Awards plugin
        $container->add(\Awards\Services\AwardsWorkflowActions::class);
        $container->add(\Awards\Services\AwardsWorkflowConditions::class);
    }
}
