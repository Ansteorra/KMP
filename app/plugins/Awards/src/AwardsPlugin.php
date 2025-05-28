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
 * Plugin for Awards
 */
class AwardsPlugin extends BasePlugin implements KMPPluginInterface
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
        $handler = new CallForCellsHandler();
        EventManager::instance()->on($handler);

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

        $currentConfigVersion = "25.01.11.a"; // update this each time you change the config

        $configVersion = StaticHelpers::getAppSetting("Awards.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("Awards.configVersion", $currentConfigVersion, null, true);
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
                    "Kanban Popup" => "selectEvent",
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
                    "Kanban Popup" => "selectGivenDate",
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
            StaticHelpers::getAppSetting("Awards.ViewConfig.Default", yaml_emit([
                'table' => [
                    'filter' => [],
                    'optionalPermission' => [],
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => true,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => true,
                        'Pronouns' => true,
                        'Pronunciation' => true,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => true,
                        'Contact Email' => true,
                        'Contact Phone' => true,
                        'Domain' => true,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => true,
                        'Given Date' => true,
                    ]
                ],
                'board' => [
                    'use' => false,
                    'states' => [],
                    'hiddenByDefault' => []
                ]
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.ViewConfig.In Progress", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->Status' => 'In Progress'],
                    'optionalPermission' => [],
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => true,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => false,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => true,
                        'Pronouns' => true,
                        'Pronunciation' => true,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => true,
                        'Contact Email' => true,
                        'Contact Phone' => true,
                        'Domain' => true,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => false,
                        'State Date' => true,
                        'Given Date' => false,
                    ]
                ],
                'board' => [
                    'use' => true,
                    'states' => [
                        'Submitted',
                        'In Consideration',
                        'Awaiting Feedback',
                        "Deferred till Later",
                        "King Approved",
                        "Queen Approved",
                        'Need to Schedule',
                        'No Action'
                    ],
                    'hiddenByDefault' => [
                        'lookback' => 30,
                        'states' => [
                            'No Action'
                        ]
                    ]
                ]
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.ViewConfig.Scheduling", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->Status' => 'Scheduling'],
                    'optionalPermission' => [],
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => false,
                        'Events' => true,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ]
                ],
                'board' => [
                    'use' => true,
                    'states' => [
                        'Need to Schedule',
                        'Scheduled',
                    ],
                    'hiddenByDefault' => [
                        'lookback' => 30,
                        'states' => []
                    ]
                ]
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.ViewConfig.To Give", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->Status' => 'To Give'],
                    'optionalPermission' => [],
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => false,
                        'For Herald' => true,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => true,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ]
                ],
                'board' => [
                    'use' => true,
                    'states' => [
                        'Scheduled',
                        'Announced Not Given',
                        'Given',
                    ],
                    'hiddenByDefault' => [
                        'lookback' => 30,
                        'states' => ["Given"]
                    ]
                ]
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.ViewConfig.Closed", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->Status' => 'Closed'],
                    'optionalPermission' => [],
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => true,
                        'Given Date' => true,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => true,
                        'Given Date' => true,
                    ]
                ],
                'board' => [
                    'use' => false,
                    'states' => [],
                    'hiddenByDefault' => [
                        'lookback' => 30,
                        'states' => []
                    ]
                ]
            ]), 'yaml', true);
            StaticHelpers::getAppSetting("Awards.ViewConfig.Event", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->event_id' => '-event_id-'],
                    'optionalPermission' => 'ViewEventRecommendations',
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => false,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => false,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => false,
                        'For' => false,
                        'For Herald' => true,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => true,
                        'Call Into Court' => true,
                        'Court Avail' => true,
                        'Person to Notify' => true,
                        'Submitted By' => true,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => false,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => false,
                    ]
                ],
                'board' => [
                    'use' => false,
                    'states' => [],
                    'hiddenByDefault' => []
                ]
            ]), 'yaml', true);

            StaticHelpers::getAppSetting("Awards.ViewConfig.SubmittedByMember", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->requester_id' => '-member_id-'],
                    'optionalPermission' => 'ViewSubmittedByMember',
                    'use' => true,
                    'enableExport' => true,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => false,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => false,
                        'Close Reason' => false,
                        'Event' => false,
                        'State Date' => false,
                        'Given Date' => false,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => false,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => false,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => false,
                        'Close Reason' => false,
                        'Event' => false,
                        'State Date' => false,
                        'Given Date' => false,
                    ]
                ],
                'board' => [
                    'use' => false,
                    'states' => [],
                    'hiddenByDefault' => []
                ]
            ]), 'yaml', true);

            StaticHelpers::getAppSetting("Awards.ViewConfig.SubmittedForMember", yaml_emit([
                'table' => [
                    'filter' => ['Recommendations->member_id' => '-member_id-'],
                    'optionalPermission' => 'ViewSubmittedForMember',
                    'use' => true,
                    'enableExport' => false,
                    'columns' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => false,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => true,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => true,
                    ],
                    'export' => [
                        'Submitted' => true,
                        'For' => true,
                        'For Herald' => false,
                        'Title' => false,
                        'Pronouns' => false,
                        'Pronunciation' => false,
                        'OP' => false,
                        'Branch' => false,
                        'Call Into Court' => false,
                        'Court Avail' => false,
                        'Person to Notify' => false,
                        'Submitted By' => true,
                        'Contact Email' => false,
                        'Contact Phone' => false,
                        'Domain' => false,
                        'Award' => true,
                        'Reason' => true,
                        'Events' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Event' => true,
                        'State Date' => false,
                        'Given Date' => true,
                    ]
                ],
                'board' => [
                    'use' => false,
                    'states' => [],
                    'hiddenByDefault' => []
                ]
            ]), 'yaml', true);
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
            'Awards',
            ['path' => '/awards'],
            function (RouteBuilder $builder) {
                // Add custom routes here
                $builder->setExtensions(["json", "pdf", "csv"]);
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
