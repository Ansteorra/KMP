<?php

declare(strict_types=1);

namespace Activities\Services;

use App\KMP\StaticHelpers;
use Activities\View\Cell\PermissionActivitiesCell;
use Activities\View\Cell\MemberAuthorizationsCell;
use Activities\View\Cell\MemberAuthorizationDetailsJSONCell;
use App\Services\ViewCellRegistry;
use App\View\Cell\BasePluginCell;

/**
 * Activities View Cell Provider Service
 * 
 * **Purpose**: Provides view cell configurations for the Activities plugin with
 * comprehensive integration support for member profiles, permission management,
 * and API endpoints.
 * 
 * **Core Responsibilities**:
 * - View Cell Registration - Complete cell configuration for Activities plugin
 * - Route-Based Cell Visibility - Context-aware cell display logic
 * - Multi-Format Support - Tab, JSON, and modal cell configurations
 * - Integration Point Management - Seamless plugin integration with core views
 * - Plugin State Management - Conditional cell registration based on availability
 * 
 * **Architecture**: 
 * This service implements the view cell provider pattern for the Activities plugin,
 * registering cells with the ViewCellRegistry for automatic rendering in appropriate
 * contexts. It supports multiple cell types and routing configurations.
 * 
 * **View Cell Types Provided**:
 * 1. **Permission Activities Tab** - Shows activities associated with permissions
 * 2. **Member Authorizations Tab** - Displays member authorization status and history
 * 3. **Member Authorization JSON** - API endpoint for authorization data
 * 
 * **Integration Contexts**:
 * - **Permission Views**: Activity listing for permission configuration
 * - **Member Profiles**: Authorization status and management interface
 * - **Mobile API**: JSON data for mobile applications and AJAX requests
 * - **Card Views**: Compact authorization displays for member cards
 * 
 * **Cell Configuration Features**:
 * - Route-based visibility controls
 * - Order-based positioning in view
 * - Badge support for notification counts
 * - Icon and label customization
 * - Multi-format rendering support
 * 
 * **ViewCellRegistry Integration**:
 * Utilizes the KMP ViewCellRegistry system for automatic cell discovery and
 * rendering in appropriate view contexts, enabling seamless plugin integration
 * without core application modifications.
 * 
 * **Performance Considerations**:
 * - Plugin availability checking prevents unnecessary processing
 * - Static method design for efficient cell configuration generation
 * - Route-based conditional loading for optimal performance
 * - Lazy loading of cell content through CakePHP cell system
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // View cells are automatically registered through ViewCellRegistry
 * // and appear in appropriate contexts:
 * 
 * // Permission view: Shows "Activities" tab with related activities
 * // Member profile: Shows "Authorizations" tab with member status
 * // Mobile API: Provides JSON data for mobile authorization displays
 * ```
 * 
 * **Cell Types**:
 * - **TAB**: Tabbed interface integration for multi-section views
 * - **JSON**: API endpoint integration for AJAX and mobile support
 * - **MODAL**: Modal dialog integration for detailed views
 * 
 * **Integration Points**:
 * - StaticHelpers::pluginEnabled() - Plugin availability validation
 * - ViewCellRegistry - Cell registration and management system
 * - BasePluginCell - Common cell functionality and patterns
 * - Activities View Cells - Actual cell implementation classes
 * 
 * **Troubleshooting**:
 * - Verify plugin is enabled in configuration
 * - Check ViewCellRegistry registration success
 * - Validate cell classes exist and are accessible
 * - Monitor cell rendering performance and content loading
 * 
 * @see ViewCellRegistry Cell registration and management
 * @see PermissionActivitiesCell Permission activity display cell
 * @see MemberAuthorizationsCell Member authorization display cell
 * @see MemberAuthorizationDetailsJSONCell JSON API cell
 * @see BasePluginCell Common plugin cell functionality
 */
class ActivitiesViewCellProvider
{
    /**
     * Provide view cell configurations for the Activities plugin used across tabs, JSON endpoints, and mobile menus.
     *
     * Generates an array of cells describing where and how Activities-related UI elements should be rendered. If the Activities plugin is disabled, an empty array is returned.
     *
     * @param array $urlParams URL parameters from the current request used to determine route-based visibility.
     * @param mixed|null $user Current authenticated user or null.
     * @return array Array of view cell configuration arrays for the Activities plugin.
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Activities')) {
            return [];
        }

        $cells = [];

        // cell for activities that have permissions
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Activities',
            'id' => 'permission-activities',
            'order' => 2,
            'tabBtnBadge' => null,
            'cell' => 'Activities.PermissionActivities',
            'validRoutes' => [
                ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
            ]
        ];

        // Cell of activities for member profiles
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Authorizations',
            'id' => 'member-authorizations',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'Activities.MemberAuthorizations',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'profile', 'plugin' => null]
            ]
        ];

        // JSON cell for member authorizations
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_JSON, // 'tab' or 'detail' or 'modal'
            'id' => 'memberAuthorizations',
            'order' => 1,
            'cell' => 'Activities.MemberAuthorizationDetailsJSON',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'viewCardJson', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null],
            ]
        ];

        // Mobile menu items for PWA card
        // These items will appear on all mobile pages (empty validRoutes = show everywhere)
        // except the page they link to (filtered out by mobile_app.php layout)

        // Request Authorization - Available to all authenticated users
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Request Authorization',
            'icon' => 'bi-file-earmark-check',
            'url' => ['controller' => 'Authorizations', 'action' => 'mobileRequestAuthorization', 'plugin' => 'Activities'],
            'order' => 10,
            'color' => 'success',
            'badge' => null,
            'validRoutes' => [], // Empty = show everywhere
            'authCallback' => function ($urlParams, $user) {
                // All authenticated users can request authorizations
                return $user !== null;
            }
        ];

        // Approve Authorizations - Only for users with pending approvals
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Approve Authorizations',
            'icon' => 'bi-check-circle',
            'url' => ['controller' => 'AuthorizationApprovals', 'action' => 'mobileApproveAuthorizations', 'plugin' => 'Activities'],
            'order' => 20,
            'color' => 'primary',
            'badge' => null, // TODO: Add count of pending approvals
            'validRoutes' => [], // Empty = show everywhere
            'authCallback' => function ($urlParams, $user) {
                if (!$user) {
                    return false;
                }

                // check if the user can access myQueue
                return $user->checkCan('myQueue', 'Activities.AuthorizationApprovals');
            }
        ];

        return $cells;
    }
}