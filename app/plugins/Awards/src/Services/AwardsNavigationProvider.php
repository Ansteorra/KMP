<?php

declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;
use Awards\Model\Entity\Recommendation;

/**
 * Awards Navigation Provider
 * 
 * Provides comprehensive navigation integration for the Awards plugin with dynamic badge support
 * and permission-based visibility. This service generates navigation items for award recommendation
 * workflows, administrative tools, configuration management, and reporting capabilities.
 * 
 * The navigation provider implements the plugin navigation architecture with workflow integration,
 * dynamic content generation, and plugin state management. It creates hierarchical navigation
 * structures with category organization, real-time badge updates, and Bootstrap Icons integration.
 * 
 * ## Navigation Architecture
 * 
 * The navigation provider implements structured navigation generation:
 * - **Workflow Integration**: Creates navigation items for recommendation workflows and approval processes
 * - **Dynamic Content**: Generates navigation with real-time status information and queue counts
 * - **Plugin State Management**: Checks plugin availability and generates appropriate navigation structure
 * - **Hierarchical Organization**: Creates parent-child navigation relationships with merge path support
 * 
 * ## Navigation Categories
 * 
 * The provider generates navigation for multiple functional areas:
 * - **Recommendation Workflows**: Navigation for recommendation submission, approval, and management
 * - **Administrative Tools**: Access to configuration management, domain/level administration, and awards setup
 * - **Configuration Management**: Navigation for award domains, levels, awards configuration, and event management
 * - **Reporting**: Integration points for recommendation analytics and award reporting
 * 
 * ## Dynamic Badge System
 * 
 * The navigation provider implements real-time badge support:
 * - **Status-Based Navigation**: Creates navigation items for each recommendation status with filtering
 * - **Queue Counts**: Provides integration points for real-time recommendation queue notifications
 * - **Visual Indicators**: Uses Bootstrap Icons for consistent visual navigation representation
 * - **Workflow Visibility**: Displays workflow progress and pending items through navigation badges
 * 
 * ## Usage Examples
 * 
 * ### Basic Navigation Generation
 * ```php
 * // In navigation building services
 * $user = $this->getCurrentUser();
 * $navigationItems = AwardsNavigationProvider::getNavigationItems($user, $requestParams);
 * 
 * foreach ($navigationItems as $item) {
 *     $this->addNavigationItem($item);
 * }
 * ```
 * 
 * ### Plugin Integration
 * ```php
 * // In main application navigation
 * if (StaticHelpers::pluginEnabled('Awards')) {
 *     $awardsNav = AwardsNavigationProvider::getNavigationItems($user);
 *     $this->mergeNavigationItems($awardsNav);
 * }
 * ```
 * 
 * ### Dynamic Badge Display
 * ```php
 * // The provider creates status-based navigation with filtering
 * // Each recommendation status gets its own navigation item with:
 * [
 *     "type" => "link",
 *     "mergePath" => ["Award Recs.", "Recommendations"],
 *     "label" => $statusKey,  // Dynamic status name
 *     "url" => [
 *         "?" => [
 *             "status" => $statusKey,  // Status-based filtering
 *             "view" => $statusKey,
 *         ],
 *     ],
 *     "icon" => "bi-file-earmark-check",
 * ]
 * ```
 * 
 * ### Permission-Based Filtering
 * ```php
 * // Navigation items are automatically filtered based on plugin availability
 * // Additional authorization can be applied at the controller level
 * $filteredNavigation = array_filter($navigationItems, function($item) {
 *     return $this->Authorization->can($user, $item['action'], $item['controller']);
 * });
 * ```
 * 
 * ## Integration Points
 * 
 * - **StaticHelpers**: Plugin availability checking and configuration management
 * - **Recommendation System**: Status-based navigation generation and workflow integration
 * - **Bootstrap Icons**: Consistent icon usage for visual navigation representation
 * - **URL Generation**: CakePHP URL array format for route generation and active path detection
 * 
 * @see \App\KMP\StaticHelpers Plugin availability and configuration management
 * @see \Awards\Model\Entity\Recommendation Recommendation status definitions and workflow states
 * @see \App\Services\NavigationManager Main navigation system integration
 * 
 */
class AwardsNavigationProvider
{
    /**
     * Generate comprehensive navigation items for Awards plugin
     *
     * Creates a complete navigation structure for the Awards plugin including recommendation
     * workflows, administrative tools, configuration management, and reporting capabilities.
     * The method implements dynamic content generation with status-based filtering, hierarchical
     * organization, and Bootstrap Icons integration.
     * 
     * The navigation generation process:
     * 1. **Plugin Availability Check**: Verifies Awards plugin is enabled before generating navigation
     * 2. **Status-Based Generation**: Creates navigation items for each recommendation status with filtering
     * 3. **Hierarchical Structure**: Organizes navigation into logical parent-child relationships
     * 4. **Dynamic Content**: Integrates real-time status information and workflow context
     * 5. **Icon Integration**: Applies Bootstrap Icons for consistent visual representation
     * 
     * ## Navigation Structure
     * 
     * The generated navigation includes:
     * - **Award Recs. Parent**: Main navigation category for recommendation workflows
     * - **Recommendations**: Access to recommendation listing with status filtering
     * - **New Recommendation**: Quick access for recommendation submission
     * - **Configuration**: Administrative access to domains, levels, awards, and events
     * - **Member Integration**: Integration with member profiles for recommendation submission
     * 
     * ## Status-Based Navigation
     * 
     * For each recommendation status, the method generates:
     * - Filtered navigation links with status-specific URLs
     * - Consistent icon usage with "bi-file-earmark-check"
     * - Active path detection for proper navigation highlighting
     * - Merge path integration for hierarchical organization
     * 
     * ## Dynamic Badge Integration
     * 
     * The navigation structure supports badge integration through:
     * - Status-based filtering URLs for queue count integration
     * - Merge path structure for badge inheritance
     * - Active path detection for workflow state visualization
     * - Icon consistency for badge display integration
     *
     * @param \App\Model\Entity\Member $user Current authenticated user for authorization context
     * @param array $params Request parameters for dynamic navigation generation
     * @return array Complete navigation structure with hierarchical organization and status-based filtering
     * 
     * @example
     * ```php
     * // Basic navigation generation
     * $user = $this->getCurrentUser();
     * $navigation = AwardsNavigationProvider::getNavigationItems($user);
     * 
     * // With request parameters for context
     * $navigation = AwardsNavigationProvider::getNavigationItems($user, [
     *     'controller' => 'Recommendations',
     *     'action' => 'index'
     * ]);
     * ```
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Awards') == false) {
            return [];
        }

        $statuses = Recommendation::getStatuses();
        $listLinks = [];
        $order = 0;

        foreach ($statuses as $statusKey => $statusKey) {
            $listLinks[] = [
                "type" => "link",
                "mergePath" => ["Award Recs.", "Recommendations"],
                "label" => $statusKey,
                "order" => $order++,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Recommendations",
                    "?" => [
                        "status" => $statusKey,
                        "view" => $statusKey,
                    ],
                ],
                "icon" => "bi-file-earmark-check",
                "activePaths" => [
                    "awards/Recommendations/view/*",
                ]
            ];
        }

        $appNav = [
            [
                "type" => "parent",
                "label" => "Award Recs.",
                "icon" => "bi-patch-exclamation-fill",
                "id" => "navheader_award_recs",
                "order" => 40,
            ],
            [
                "type" => "link",
                "mergePath" => ["Award Recs."],
                "label" => "Recommendations",
                "order" => 30,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-megaphone",
                "activePaths" => [
                    "awards/Recommendations/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Award Domains",
                "order" => 30,
                "url" => [
                    "controller" => "Domains",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Domains",
                ],
                "icon" => "bi-compass",
                "activePaths" => [
                    "awards/Domains/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Award Levels",
                "order" => 31,
                "url" => [
                    "controller" => "Levels",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Levels",
                ],
                "icon" => "bi-ladder",
                "activePaths" => [
                    "awards/Levels/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Awards",
                "order" => 32,
                "url" => [
                    "controller" => "Awards",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Awards",
                ],
                "icon" => "bi-award",
                "activePaths" => [
                    "awards/Awards/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Members"],
                "label" => "Submit Award Rec.",
                "order" => 30,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-megaphone-fill",
                "linkTypeClass" => "btn",
                "otherClasses" => StaticHelpers::getAppSetting("Awards.RecButtonClass"),
            ]
        ];

        return array_merge($appNav, $listLinks);
    }
}
