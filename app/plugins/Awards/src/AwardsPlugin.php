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
            StaticHelpers::getAppSetting("Awards.RecommendationStatesRequireCanViewHidden", yaml_emit([
                "No Action",
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.RecommendationStatuses", yaml_emit([
                "In Progress" => [
                    "Submitted",
                    "In Consideration",
                    "Awaiting Feedback",
                    "Deferred till Later",
                    "King Approved",
                    "Queen Approved",
                ],
                "Scheduling" => [
                    "Need to Schedule",
                ],
                "To Give" => [
                    "Scheduled",
                    "Announced Not Given",
                ],
                "Closed" => [
                    "Given",
                    "No Action",
                ],
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.RecommendationStateRules", yaml_emit([
                "Need to Schedule" => [
                    "Visible" => [
                        "planToGiveBlockTarget",
                    ],
                    "Disabled" => [
                        "domainTarget",
                        "awardTarget",
                        "specialtyTarget",
                        "scaMemberTarget",
                        "branchTarget",
                        "scaMemberTarget",
                    ],
                ],
                "Scheduled" => [
                    "Required" => [
                        "planToGiveEventTarget"
                    ],
                    "Visible" => [
                        "planToGiveBlockTarget",
                    ],
                    "Disabled" => [
                        "domainTarget",
                        "awardTarget",
                        "specialtyTarget",
                        "scaMemberTarget",
                        "branchTarget",
                        "scaMemberTarget",
                    ],
                ],
                "Given" => [
                    "Required" => [
                        "planToGiveEventTarget",
                        "givenDateTarget"
                    ],
                    "Visible" => [
                        "planToGiveBlockTarget",
                        "givenBlockTarget"
                    ],
                    "Disabled" => [
                        "domainTarget",
                        "awardTarget",
                        "specialtyTarget",
                        "scaMemberTarget",
                        "branchTarget",
                        "scaMemberTarget",
                    ],
                    "Set" =>
                    [
                        "close_reason" => "Given"
                    ]
                ],
                "No Action" => [
                    "Required" => [
                        "closeReasonTarget",
                    ],
                    "Visible" => [
                        "closeReasonBlockTarget",
                        "closeReasonTarget",
                    ],
                    "Disabled" => [
                        "domainTarget",
                        "awardTarget",
                        "specialtyTarget",
                        "scaMemberTarget",
                        "branchTarget",
                        "courtAvailabilityTarget",
                        "callIntoCourtTarget",
                        "scaMemberTarget",
                    ],
                ],
            ]), 'yaml', true);
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
    public function services(ContainerInterface $container): void {}
}
