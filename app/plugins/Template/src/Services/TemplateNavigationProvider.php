<?php

declare(strict_types=1);

namespace Template\Services;

use App\KMP\StaticHelpers;

/**
 * Template Navigation Provider
 *
 * This service provides navigation menu items for the Template plugin.
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
 * @package Template\Services
 */
class TemplateNavigationProvider
{
    /**
     * Get Navigation Items
     *
     * Returns an array of navigation items for the Template plugin.
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
        // Check if the plugin is enabled in settings
        $enabled = StaticHelpers::getAppSetting('Plugin.Template.Active', 'yes');

        if ($enabled !== 'yes') {
            return [];
        }

        $items = [];

        // Parent section for Template plugin navigation
        $items[] = [
            "type" => "parent",
            "label" => "Template",
            "icon" => "bi-puzzle",
            "id" => "navheader_template",
            "order" => 900,
        ];

        // Main Hello World menu item under the Template parent section
        $items[] = [
            "type" => "link",
            "mergePath" => ["Template"],
            "label" => "Hello World",
            "order" => 10,
            "url" => [
                "controller" => "HelloWorld",
                "action" => "index",
                "plugin" => "Template",
            ],
            "icon" => "bi-globe",
            "activePaths" => [
                "template/HelloWorld/view/*",
                "template/HelloWorld/edit/*",
            ]
        ];

        // Add new item under Template submenu
        if ($user !== null) {
            $items[] = [
                "type" => "link",
                "mergePath" => ["Template", "Hello World"],
                "label" => "Add New",
                "order" => 0,
                "url" => [
                    "controller" => "HelloWorld",
                    "action" => "add",
                    "plugin" => "Template",
                ],
                "icon" => "bi-plus-circle",
            ];
        }

        // Example: Add to user's personal menu
        if ($user !== null && method_exists($user, '__get')) {
            $userName = $user->sca_name ?? $user->username ?? 'User';
            $items[] = [
                "type" => "link",
                "mergePath" => ["Members", $userName],
                "label" => "My Hello Items",
                "order" => 50,
                "url" => [
                    "controller" => "HelloWorld",
                    "action" => "index",
                    "plugin" => "Template",
                    "?" => ["member_id" => $user->id ?? null]
                ],
                "icon" => "bi-person-lines-fill",
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