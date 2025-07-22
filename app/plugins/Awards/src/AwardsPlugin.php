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
 * Awards Plugin - Award Recommendation Management System
 * 
 * This plugin provides comprehensive award recommendation management for the KMP system,
 * including a complex state machine for recommendation workflow, hierarchical award organization,
 * ceremony coordination, and integration with the KMP member and branch management systems.
 * 
 * The Awards plugin implements a sophisticated recommendation lifecycle with configurable
 * status/state dual tracking, multi-level approval workflows, event coordination for award
 * ceremonies, and comprehensive reporting capabilities. It integrates deeply with the KMP
 * RBAC system for permission-based access control and warrant validation.
 * 
 * ## Core Features:
 * - **Award Hierarchy Management**: Domain, Level, and Award organization with branch scoping
 * - **Recommendation State Machine**: Complex status/state workflow with configurable transitions
 * - **Event Coordination**: Award ceremony planning and scheduling integration
 * - **Member Integration**: Deep integration with member profiles and branch hierarchy
 * - **RBAC Integration**: Permission-based access control with warrant validation
 * - **Audit Trail**: Comprehensive state logging and accountability tracking
 * - **Reporting System**: Analytics and export capabilities for award management
 * 
 * ## Architecture:
 * The plugin uses a sophisticated state machine architecture where recommendations have both
 * a "status" (category grouping) and "state" (specific workflow position). This allows for
 * flexible workflow management while maintaining clear categorization for reporting and UI
 * organization. The plugin integrates with the KMP navigation system and view cell registry
 * for seamless UI integration.
 * 
 * ## Configuration Management:
 * The plugin implements version-controlled configuration management with automatic updates
 * for recommendation statuses, state rules, view configurations, and UI customization
 * settings. Configuration changes are tracked through version numbers and automatically
 * applied during plugin bootstrap.
 * 
 * @package Awards
 * @see AwardsNavigationProvider For navigation integration
 * @see AwardsViewCellProvider For view cell integration
 * @see StaticHelpers For configuration management
 */
class AwardsPlugin extends BasePlugin implements KMPPluginInterface
{

    /**
     * Plugin migration order for KMP plugin system
     * 
     * @var int Migration order priority for database setup
     */
    protected int $_migrationOrder = 0;

    /**
     * Get migration order for KMP plugin system
     * 
     * Returns the migration order priority for this plugin, which determines
     * the sequence in which plugin migrations are executed during system setup.
     * The Awards plugin uses default order (0) as it depends on core KMP tables
     * but doesn't require special ordering relative to other plugins.
     * 
     * @return int Migration order priority
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * Constructor - Initialize Awards plugin with migration configuration
     * 
     * Sets up the plugin with proper migration ordering for the KMP plugin system.
     * The migration order determines when this plugin's database migrations are
     * executed relative to other plugins during system initialization.
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
     * Bootstrap Awards Plugin - Initialize navigation, view cells, and configuration
     * 
     * This method performs comprehensive plugin initialization including:
     * - Navigation system registration with dynamic permission-based items
     * - View cell registry integration for UI component embedding
     * - Version-controlled configuration management with automatic updates
     * - Award recommendation status and state workflow configuration
     * - UI customization settings and display rules
     * 
     * The bootstrap process implements a sophisticated configuration versioning system
     * that automatically updates plugin settings when the configuration version changes.
     * This ensures that configuration updates are properly applied during deployments
     * without requiring manual intervention.
     * 
     * ## Navigation Integration:
     * Registers the Awards plugin with the KMP navigation system using dynamic
     * navigation providers that generate menu items based on user permissions
     * and current workflow state. Navigation items include badges for pending
     * items and permission-based visibility.
     * 
     * ## View Cell Integration:
     * Registers view cells with the ViewCellRegistry for embedding Awards-related
     * UI components throughout the KMP application. View cells provide contextual
     * recommendation information and workflow interfaces.
     * 
     * ## Configuration Management:
     * Implements version-controlled configuration with the following settings:
     * - Recommendation status and state workflow definitions
     * - State transition rules and validation requirements
     * - UI display configuration for table and board views
     * - Member profile integration settings for court protocols
     * - Plugin activation and feature toggles
     * 
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     * 
     * @see AwardsNavigationProvider::getNavigationItems() For navigation generation
     * @see AwardsViewCellProvider::getViewCells() For view cell registration
     * @see StaticHelpers::getAppSetting() For configuration management
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
     * Configure Awards Plugin Routes - RESTful routing with multi-format support
     * 
     * Establishes the routing configuration for the Awards plugin with comprehensive
     * format support for data export and API access. The route configuration supports
     * JSON for AJAX endpoints, PDF for report generation, and CSV for data export.
     * 
     * ## Route Configuration:
     * - **Base Path**: `/awards` - All Awards plugin routes are scoped under this path
     * - **Format Support**: JSON, PDF, CSV extensions for flexible data access
     * - **Fallback Routes**: Automatic RESTful route generation for all controllers
     * 
     * ## Supported Formats:
     * - **JSON**: AJAX endpoints for dynamic UI updates and mobile app integration
     * - **PDF**: Report generation for award recommendations and ceremony planning
     * - **CSV**: Data export for external analysis and administrative reporting
     * 
     * The routing system integrates with CakePHP's automatic route generation while
     * providing explicit format support for Awards-specific functionality such as
     * recommendation reporting, ceremony coordination, and administrative exports.
     * 
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update
     * @return void
     * 
     * @see RouteBuilder::fallbacks() For automatic RESTful route generation
     * @see RouteBuilder::setExtensions() For multi-format support configuration
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
     * Configure Awards Plugin Middleware - Request processing pipeline
     * 
     * Configures the middleware pipeline for the Awards plugin. Currently uses
     * the default middleware configuration without additional middleware layers.
     * Future middleware additions might include:
     * 
     * ## Potential Middleware:
     * - **Audit Logging**: Track recommendation state changes and administrative actions
     * - **Rate Limiting**: Prevent abuse of recommendation submission endpoints
     * - **Caching**: Cache award hierarchy and configuration data for performance
     * - **Validation**: Additional request validation for complex workflows
     * 
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update
     * @return \Cake\Http\MiddlewareQueue Updated middleware queue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Add your middlewares here

        return $middlewareQueue;
    }

    /**
     * Register Awards Plugin Console Commands - CLI interface for administrative tasks
     * 
     * Registers console commands for the Awards plugin to provide CLI access for
     * administrative tasks, data maintenance, and batch operations. Currently uses
     * the default command registration without additional custom commands.
     * 
     * ## Potential Commands:
     * - **Recommendation Cleanup**: Automated cleanup of abandoned recommendations
     * - **State Migration**: Batch update of recommendation states during workflow changes
     * - **Report Generation**: Automated generation of periodic award reports
     * - **Data Validation**: Validation and cleanup of award hierarchy data
     * - **Event Processing**: Batch processing of ceremony scheduling and notifications
     * 
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection Updated command collection with Awards commands
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        // Add your commands here

        $commands = parent::console($commands);

        return $commands;
    }

    /**
     * Register Awards Plugin Services - Dependency injection container configuration
     * 
     * Registers services with the dependency injection container for the Awards plugin.
     * Currently uses default service registration without additional custom services.
     * 
     * ## Potential Services:
     * - **RecommendationWorkflowManager**: Business logic for recommendation state machine
     * - **CeremonyCoordinator**: Event scheduling and coordination services
     * - **AwardHierarchyManager**: Award domain/level/branch relationship management
     * - **NotificationService**: Email and notification management for workflows
     * - **ReportGenerator**: Report generation and export services
     * 
     * The service container integration allows for clean dependency injection and
     * testable service architecture for complex Awards plugin functionality.
     * 
     * @param \Cake\Core\ContainerInterface $container The container to update
     * @return void
     * 
     * @see https://book.cakephp.org/4/en/development/dependency-injection.html
     */
    public function services(ContainerInterface $container): void
    {
        // Add your services here
    }
}
