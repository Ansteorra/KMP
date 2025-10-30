<?php

declare(strict_types=1);

namespace Officers\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Officers Navigation Provider Service
 * 
 * Provides comprehensive navigation menu integration for the Officers plugin within the KMP
 * application navigation system. This service generates navigation items for officer management,
 * administrative configuration, reporting capabilities, and organizational management with
 * dynamic visibility, permission-based access control, and comprehensive menu organization.
 * 
 * The OfficersNavigationProvider replaces the legacy event-based navigation system with a
 * modern service-oriented approach providing better performance, clearer organization,
 * and enhanced maintainability for officers plugin navigation integration.
 * 
 * ## Navigation Architecture
 * 
 * **Plugin Integration**: Integrates with KMP navigation system through static service
 * method providing navigation items for plugin integration, menu organization, and
 * comprehensive navigation structure for officers plugin functionality.
 * 
 * **Dynamic Visibility**: Implements dynamic navigation visibility based on plugin
 * availability, user context, permission validation, and organizational requirements
 * for appropriate navigation display and access control management.
 * 
 * **Hierarchical Organization**: Organizes navigation items in hierarchical structure
 * including merge paths, ordering, categorization, and comprehensive menu organization
 * for logical navigation flow and user experience optimization.
 * 
 * **Route Integration**: Integrates with CakePHP routing system through URL generation,
 * active path matching, controller coordination, and comprehensive route management
 * for accurate navigation and application state tracking.
 * 
 * ## Navigation Categories
 * 
 * **Officer Management**: Provides navigation items for officer lifecycle management
 * including officer listing, assignment workflows, reporting capabilities, and
 * comprehensive officer administration for organizational management.
 * 
 * **Administrative Configuration**: Provides navigation items for administrative
 * configuration including department management, office configuration, hierarchical
 * setup, and comprehensive administrative tools for organizational structure.
 * 
 * **Reporting and Analytics**: Provides navigation items for reporting capabilities
 * including department rosters, officer analytics, organizational reporting, and
 * comprehensive reporting tools for administrative oversight and compliance.
 * 
 * **Security and Compliance**: Provides navigation items for security operations
 * including roster generation, warrant management, compliance tracking, and
 * comprehensive security tools for organizational governance and accountability.
 * 
 * ## Navigation Item Structure
 * 
 * **Link Configuration**: Configures navigation links including URL generation,
 * controller specification, action definition, plugin identification, and
 * comprehensive routing configuration for accurate navigation functionality.
 * 
 * **Visual Integration**: Integrates with Bootstrap Icons for visual consistency,
 * icon specification, theme coordination, and comprehensive visual integration
 * for professional navigation appearance and user experience.
 * 
 * **Active Path Management**: Manages active path detection including pattern matching,
 * navigation state tracking, visual feedback, and comprehensive navigation state
 * management for accurate user interface feedback and navigation awareness.
 * 
 * **Merge Path Organization**: Organizes navigation items through merge paths enabling
 * hierarchical menu structure, category organization, logical grouping, and
 * comprehensive menu organization for intuitive navigation and user experience.
 * 
 * ## Plugin State Management
 * 
 * **Plugin Availability Checking**: Validates plugin availability through StaticHelpers
 * integration ensuring navigation items only appear when plugin is enabled and
 * functional for appropriate navigation display and system integrity.
 * 
 * **Dynamic Content Generation**: Generates navigation content dynamically based on
 * plugin state, user context, system configuration, and organizational requirements
 * for appropriate navigation customization and user experience optimization.
 * 
 * ## Performance Considerations
 * 
 * **Static Method Implementation**: Implements static method for navigation generation
 * optimizing performance, reducing object instantiation overhead, and providing
 * efficient navigation processing for high-performance application operation.
 * 
 * **Conditional Loading**: Implements conditional navigation loading based on plugin
 * availability reducing unnecessary processing, optimizing resource utilization,
 * and providing efficient navigation generation for system performance.
 * 
 * ## Integration Points
 * 
 * **StaticHelpers Integration**: Integrates with StaticHelpers for plugin availability
 * checking, system state validation, configuration management, and comprehensive
 * system integration for reliable navigation generation and plugin coordination.
 * 
 * **Bootstrap Icons Integration**: Integrates with Bootstrap Icons for visual consistency,
 * icon management, theme coordination, and comprehensive visual integration
 * for professional navigation appearance and user interface consistency.
 * 
 * **CakePHP Routing Integration**: Integrates with CakePHP routing system for URL
 * generation, route management, controller coordination, and comprehensive routing
 * integration for accurate navigation functionality and application coordination.
 * 
 * @package Officers\Services
 * @since 1.0.0
 * @version 2.0.0
 */
class OfficersNavigationProvider
{
    /**
     * Builds navigation items for the Officers plugin.
     *
     * Generates the structured navigation definitions for officer management, configuration,
     * reporting, and roster actions. Items are produced only when the Officers plugin is enabled.
     *
     * @param Member $user The current authenticated user (context for visibility/permissions).
     * @param array $params Optional request or context parameters to customize generation.
     * @return array An array of navigation item definitions. Each item includes keys such as
     *               `type`, `mergePath`, `label`, `order`, `url` (plugin/controller/action/model),
     *               `icon`, and optionally `activePaths`.
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Officers') == false) {
            return [];
        }

        return [
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Officers",
                "order" => 29,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Officers",
                    "action" => "index",
                    "model" => "Officers.Officers",
                ],
                "icon" => "bi-building",
                "activePaths" => [
                    "officers/Officers/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Departments",
                "order" => 40,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Departments",
                    "action" => "index",
                    "model" => "Officers.Departments",
                ],
                "icon" => "bi-building",
                "activePaths" => [
                    "officers/departments/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Offices",
                "order" => 50,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Offices",
                    "action" => "index",
                    "model" => "Officers.Offices",
                ],
                "icon" => "bi-person-gear",
                "activePaths" => [
                    "officers/offices/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Dept. Officer Roster",
                "order" => 20,
                "url" => [
                    "controller" => "Reports",
                    "action" => "DepartmentOfficersRoster",
                    "plugin" => "Officers",
                ],
                "icon" => "bi-building-fill-check",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security", "Rosters"],
                "label" => "New Officer Roster",
                "order" => 20,
                "url" => [
                    "controller" => "Rosters",
                    "action" => "add",
                    "plugin" => "Officers",
                ],
                "icon" => "bi-plus",
            ],
        ];
    }
}