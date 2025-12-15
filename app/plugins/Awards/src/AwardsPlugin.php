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

        $currentConfigVersion = "25.11.30.a"; // DVGrid migration - retiring Kanban board

        $configVersion = StaticHelpers::getAppSetting("Awards.configVersion", "0.0.0", null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting("Awards.configVersion", $currentConfigVersion, null, true);

            // Migrate existing view configs: replace Event/Events with Gathering/Gatherings
            $viewConfigs = [
                'Awards.ViewConfig.Default',
                'Awards.ViewConfig.In Progress',
                'Awards.ViewConfig.Scheduling',
                'Awards.ViewConfig.To Give',
                'Awards.ViewConfig.Closed',
                'Awards.ViewConfig.Event',
                'Awards.ViewConfig.SubmittedByMember',
                'Awards.ViewConfig.SubmittedForMember',
            ];

            foreach ($viewConfigs as $configKey) {
                $config = StaticHelpers::getAppSetting($configKey, null, 'yaml');
                if ($config !== null && is_array($config)) {
                    // Update column names in table columns
                    if (isset($config['table']['columns'])) {
                        $config['table']['columns'] = $this->migrateColumnNames($config['table']['columns']);
                    }
                    // Update column names in export columns
                    if (isset($config['table']['export'])) {
                        $config['table']['export'] = $this->migrateColumnNames($config['table']['export']);
                    }
                    // Update filter for Event config
                    if ($configKey === 'Awards.ViewConfig.Event' && isset($config['table']['filter'])) {
                        $newFilter = [];
                        foreach ($config['table']['filter'] as $key => $value) {
                            if ($key === 'Recommendations->event_id') {
                                $newFilter['Recommendations->gathering_id'] = str_replace('-event_id-', '-gathering_id-', $value);
                            } else {
                                $newFilter[$key] = $value;
                            }
                        }
                        $config['table']['filter'] = $newFilter;
                    }
                    // Update permission for Event config
                    if ($configKey === 'Awards.ViewConfig.Event' && isset($config['table']['optionalPermission'])) {
                        if ($config['table']['optionalPermission'] === 'ViewEventRecommendations') {
                            $config['table']['optionalPermission'] = 'ViewGatheringRecommendations';
                        }
                    }
                    // Save updated config
                    StaticHelpers::setAppSetting($configKey, yaml_emit($config), 'yaml', true);
                }
            }

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
                        'Gatherings' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
                        'Gatherings' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
                        'Gatherings' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => false,
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
                        'Gatherings' => true,
                        'Notes' => true,
                        'Status' => true,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => false,
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
                        'Gatherings' => true,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => true,
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
                        'Gatherings' => true,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => true,
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
                        'Gatherings' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => true,
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
                        'Gatherings' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => true,
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
                        'Gatherings' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
                        'Gatherings' => false,
                        'Notes' => true,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
                    'filter' => ['Recommendations->gathering_id' => '-gathering_id-'],
                    'optionalPermission' => 'ViewGatheringRecommendations',
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
                        'Gatherings' => false,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => false,
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
                        'Gatherings' => false,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => false,
                        'Gathering' => true,
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
                        'Gatherings' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => false,
                        'Close Reason' => false,
                        'Gathering' => false,
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
                        'Gatherings' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => false,
                        'Close Reason' => false,
                        'Gathering' => false,
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
                        'Gatherings' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
                        'Gatherings' => true,
                        'Notes' => false,
                        'Status' => false,
                        'State' => true,
                        'Close Reason' => true,
                        'Gathering' => true,
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
     * Migrate column names from Event/Events to Gathering/Gatherings.
     *
     * @param array $columns Column configuration array
     * @return array Updated column configuration with migrated names
     */
    private function migrateColumnNames(array $columns): array
    {
        $migrated = [];
        foreach ($columns as $key => $value) {
            if ($key === 'Event') {
                $migrated['Gathering'] = $value;
            } elseif ($key === 'Events') {
                $migrated['Gatherings'] = $value;
            } else {
                $migrated[$key] = $value;
            }
        }
        return $migrated;
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
