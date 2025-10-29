<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\KMP\StaticHelpers;

/**
 * Waivers Navigation Provider
 *
 * This service provides navigation menu items for the Waivers plugin.
 * It integrates with KMP's NavigationRegistry to add plugin-specific
 * menu items to the main application navigation.
 *
 * ## Navigation Architecture
 *
 * KMP uses a service-based navigation system where plugins register
 * navigation providers that dynamically generate menu items based on:
 * - User permissions and roles
 * - Current context (branch, member, etc.)
 * - Plugin configuration settings
 * - Active warrants and authorizations
 *
 * ## Menu Item Structure
 *
 * Each navigation item is an array with:
 * - **label**: Display text for the menu item
 * - **url**: URL array or string for the link
 * - **icon**: Bootstrap icon class (optional)
 * - **order**: Sort order in the menu
 * - **children**: Sub-menu items (optional)
 * - **badge**: Notification badge (optional)
 * - **active**: Callback to determine if item is active
 *
 * ## Dynamic Generation
 *
 * Navigation items can be generated dynamically based on:
 * - User identity and permissions
 * - Request parameters
 * - Database queries
 * - Plugin settings
 *
 * @package Waivers\Services
 */
class WaiversNavigationProvider
{
    /**
     * Get Navigation Items
     *
     * Returns an array of navigation items for the Waivers plugin.
     * This method is called by the NavigationRegistry when building
     * the application menu.
     *
     * Navigation items use the KMP navigation format with:
     * - type: "link" for navigation links
     * - mergePath: Hierarchical path array for menu organization
     * - label: Display text
     * - order: Sort order within the mergePath
     * - url: CakePHP URL array
     * - icon: Bootstrap icon class
     * - badgeValue: Optional dynamic badge configuration
     * - activePaths: Optional array of paths that highlight this nav item
     *
     * @param mixed $user The current user identity (null if not logged in)
     * @param array $params Request parameters and context
     * @return array Array of navigation items in KMP format
     */
    public static function getNavigationItems($user, array $params = []): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Waivers')) {
            return [];
        }

        $items = [];

        // Parent section for Waivers plugin navigation
        $items[] = [
            "type" => "parent",
            "label" => "Waivers",
            "icon" => "bi-file-earmark-text",
            "id" => "navheader_waivers",
            "order" => 600,
        ];

        // Waiver Secretary Dashboard - primary navigation item
        if ($user !== null) {
            $items[] = [
                "type" => "link",
                "mergePath" => ["Waivers"],
                "label" => "All Waivers",
                "order" => 6,
                "url" => [
                    "controller" => "GatheringWaivers",
                    "action" => "index",
                    "plugin" => "Waivers",
                    "model" => "Waivers.GatheringWaivers"
                ],
                "icon" => "bi-list-ul",
                "activePaths" => [
                    "waivers/GatheringWaivers/dashboard",
                ]
            ];
            $items[] = [
                "type" => "link",
                "mergePath" => ["Waivers"],
                "label" => "Waiver Dashboard",
                "order" => 5,
                "url" => [
                    "controller" => "GatheringWaivers",
                    "action" => "dashboard",
                    "plugin" => "Waivers",
                ],
                "icon" => "bi-speedometer2",
                "activePaths" => [
                    "waivers/GatheringWaivers/dashboard",
                ]
            ];
        }

        // Waiver Types menu item
        $items[] = [
            "type" => "link",
            "mergePath" => ["Config"],
            "label" => "Waiver Types",
            "order" => 100,
            "url" => [
                "controller" => "WaiverTypes",
                "action" => "index",
                "plugin" => "Waivers",
                "model" => "Waivers.WaiverTypes"
            ],
            "icon" => "bi-file-earmark-ruled",
            "activePaths" => [
                "waivers/WaiverTypes/view/*",
                "waivers/WaiverTypes/edit/*",
            ]
        ];

        // Add "Gatherings Needing Waivers" to Actions Parent if user is logged in
        if ($user !== null) {
            $items[] = [
                "type" => "link",
                "mergePath" => ["Action Items"],
                "label" => "Gatherings Needing Waivers",
                "order" => 25,  // Order within Actions Parent section
                "url" => [
                    "controller" => "GatheringWaivers",
                    "action" => "needingWaivers",
                    "plugin" => "Waivers",
                ],
                "icon" => "bi-file-earmark-check",
                "badgeClass" => "bg-danger",
                "badgeValue" => [
                    "class" => "Waivers\Model\Table\GatheringWaiversTable",
                    "method" => "countGatheringsNeedingWaivers",
                    "argument" => $user->getIdentifier()
                ],
                "activePaths" => [
                    "waivers/GatheringWaivers/needingWaivers",
                ]
            ];
        }

        // Example: Menu item with dynamic badge (notification count)
        // Uncomment and customize for real implementation
        // if ($user !== null) {
        //     $items[] = [
        //         "type" => "link",
        //         "mergePath" => ["Action Items"],
        //         "label" => "Pending Items",
        //         "order" => 30,
        //         "url" => [
        //             "controller" => "HelloWorld",
        //             "action" => "pending",
        //             "plugin" => "Template",
        //         ],
        //         "icon" => "bi-exclamation-circle",
        //         "badgeClass" => "bg-danger",
        //         "badgeValue" => [
        //             "class" => "Template\Model\Table\HelloWorldItemsTable",
        //             "method" => "pendingCount",
        //             "argument" => $user->id
        //         ],
        //     ];
        // }

        return $items;
    }

    /**
     * Get navigation items for member context
     *
     * Returns navigation items specific to a member's profile page.
     * These items appear in member-specific navigation areas.
     *
     * @param mixed $user The current user identity
     * @param int $memberId The member being viewed
     * @param array $params Additional parameters
     * @return array Array of navigation items
     */
    public static function getMemberNavigationItems($user, int $memberId, array $params = []): array
    {
        $items = [];

        // Example: Add member-specific menu items
        // $items[] = [
        //     'label' => 'Member Hello Items',
        //     'url' => [
        //         'plugin' => 'Template',
        //         'controller' => 'HelloWorld',
        //         'action' => 'member',
        //         $memberId,
        //     ],
        // ];

        return $items;
    }

    /**
     * Get navigation items for branch context
     *
     * Returns navigation items specific to a branch page.
     * These items appear in branch-specific navigation areas.
     *
     * @param mixed $user The current user identity
     * @param int $branchId The branch being viewed
     * @param array $params Additional parameters
     * @return array Array of navigation items
     */
    public static function getBranchNavigationItems($user, int $branchId, array $params = []): array
    {
        $items = [];

        // Example: Add branch-specific menu items
        // $items[] = [
        //     'label' => 'Branch Hello Items',
        //     'url' => [
        //         'plugin' => 'Template',
        //         'controller' => 'HelloWorld',
        //         'action' => 'branch',
        //         $branchId,
        //     ],
        // ];

        return $items;
    }

    /**
     * Example helper: Get unread count for notification badge
     *
     * @param mixed $user The current user identity
     * @return int Number of unread items
     */
    protected static function getUnreadCount($user): int
    {
        if ($user === null) {
            return 0;
        }

        // In a real implementation, query the database
        // return TableRegistry::getTableLocator()
        //     ->get('Template.HelloWorldItems')
        //     ->find()
        //     ->where(['member_id' => $user->getIdentifier(), 'read' => false])
        //     ->count();

        return 0;
    }
}
