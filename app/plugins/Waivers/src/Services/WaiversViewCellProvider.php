<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\KMP\StaticHelpers;
use App\Services\ViewCellRegistry;
use App\View\Cell\BasePluginCell;

/**
 * Waivers View Cell Provider Service
 * 
 * **Purpose**: Provides view cell configurations for the Waivers plugin with
 * comprehensive integration support for gathering activities, waiver management,
 * and compliance tracking.
 * 
 * **Core Responsibilities**:
 * - View Cell Registration - Complete cell configuration for Waivers plugin
 * - Route-Based Cell Visibility - Context-aware cell display logic
 * - Multi-Format Support - Tab, JSON, and modal cell configurations
 * - Integration Point Management - Seamless plugin integration with core views
 * - Plugin State Management - Conditional cell registration based on availability
 * 
 * **Architecture**: 
 * This service implements the view cell provider pattern for the Waivers plugin,
 * registering cells with the ViewCellRegistry for automatic rendering in appropriate
 * contexts. It supports multiple cell types and routing configurations.
 * 
 * **View Cell Types Provided**:
 * 1. **Gathering Activity Waivers Tab** - Shows waiver requirements for activities
 * 2. **Gathering Waivers Tab** - Comprehensive waiver view (requirements + upload/management)
 * 
 * **Integration Contexts**:
 * - **Gathering Activity Views**: Waiver requirement display and management
 * - **Gathering Views**: Aggregated waiver requirements across all activities
 * - **Waiver Management**: Configuration of activity-specific waiver requirements
 * - **Compliance Tracking**: Waiver coverage status for activities and gatherings
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
 * // Gathering Activity view: Shows "Waivers" tab with required waivers
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
 * - Waivers View Cells - Actual cell implementation classes
 * 
 * **Troubleshooting**:
 * - Verify plugin is enabled in configuration
 * - Check ViewCellRegistry registration success
 * - Validate cell classes exist and are accessible
 * - Monitor cell rendering performance and content loading
 * 
 * @see ViewCellRegistry Cell registration and management
 * @see GatheringActivityWaiversCell Gathering activity waiver display cell
 * @see BasePluginCell Common plugin cell functionality
 */
class WaiversViewCellProvider
{
    /**
     * Get Waivers Plugin View Cells
     *
     * Generates complete view cell configurations for Waivers plugin integration
     * with gathering activity management and waiver compliance tracking.
     * 
     * **Cell Configuration Structure**:
     * Each cell includes:
     * - Type: TAB, JSON, or MODAL for different integration contexts
     * - Label: User-visible text for tab headers and navigation
     * - ID: Unique identifier for cell targeting and customization
     * - Order: Positioning within view containers
     * - Cell: CakePHP cell class path for rendering
     * - Valid Routes: Route specifications for conditional display
     * 
     * **Gathering Activity Waivers Cell**:
     * - **Context**: Gathering activity detail views
     * - **Purpose**: Shows waiver requirements for the activity
     * - **Integration**: Activity configuration and compliance workflow
     * - **Display**: Tabbed interface with waiver requirement listing
     * 
     * **Gathering Waivers Cell**:
     * - **Context**: Gathering detail views
     * - **Purpose**: Comprehensive waiver view combining requirements and upload/management
     * - **Integration**: Complete waiver lifecycle from configuration to collection
     * - **Display**: Tabbed interface with requirements table, upload stats, and management tools
     * 
     * **Route-Based Visibility**:
     * Cells are conditionally displayed based on current route context,
     * ensuring appropriate integration without cluttering unrelated views.
     * 
     * **Plugin Availability Check**:
     * Returns empty array if Waivers plugin is disabled, preventing
     * error conditions and maintaining application stability.
     * 
     * @param array $urlParams URL parameters from current request context
     * @param mixed $user Current authenticated user (may be null for API calls)
     * @return array Complete view cell configurations for Waivers plugin
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Waivers')) {
            return [];
        }

        $cells = [];

        // Cell for waiver requirements on gathering activities
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Required Waivers',
            'id' => 'gathering-activity-waivers',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'Waivers.GatheringActivityWaivers',
            'validRoutes' => [
                ['controller' => 'GatheringActivities', 'action' => 'view', 'plugin' => null],
            ],
            'authCallback' => function ($urlParams, $user) {
                if (!$user) {
                    return false;
                }

                // Get the gathering ID from URL parameters
                $gatheringId = $urlParams['pass'][0] ?? null;
                if (!$gatheringId) {
                    return false;
                }

                // Create an empty GatheringWaiver entity with gathering context
                // This allows the policy to use getBranchId() to determine the hosting branch
                $gatheringWaiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
                $tempWaiver = $gatheringWaiversTable->newEmptyEntity();
                $tempWaiver->gathering_id = $gatheringId;

                // Check if user has canViewGatheringWaivers permission for this branch
                return $user->checkCan('ViewGatheringWaivers', $tempWaiver);
            }
        ];

        // Cell for comprehensive waiver display on gatherings (requirements + upload/management)
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Waivers',
            'id' => 'gathering-waivers',
            'order' => 10,
            'tabBtnBadge' => null,
            'cell' => 'Waivers.GatheringWaivers',
            'validRoutes' => [
                ['controller' => 'Gatherings', 'action' => 'view', 'plugin' => null],
            ],
            'authCallback' => function ($urlParams, $user) {
                if (!$user) {
                    return false;
                }

                // Get the gathering ID from URL parameters
                $gatheringId = $urlParams['pass'][0] ?? null;
                if (!$gatheringId) {
                    return false;
                }
                if (!is_numeric($gatheringId)) {
                    // get the gathering by public_id
                    $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
                    $gathering = $gatheringsTable->find()
                        ->where(['public_id' => $gatheringId])
                        ->select(['id'])
                        ->first();
                    if (!$gathering) {
                        return false;
                    }
                    $gatheringId = $gathering->id;
                }

                // Create an empty GatheringWaiver entity with gathering context
                // This allows the policy to use getBranchId() to determine the hosting branch
                $gatheringWaiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
                $tempWaiver = $gatheringWaiversTable->newEmptyEntity();
                $tempWaiver->gathering_id = $gatheringId;

                // Check if user has canViewGatheringWaivers permission for this branch
                return $user->checkCan('ViewGatheringWaivers', $tempWaiver);
            }
        ];

        // Mobile menu items for PWA card
        // This item will appear on all mobile pages (empty validRoutes = show everywhere)
        // except the page it links to (filtered out by mobile_app.php layout)

        // Submit Waiver - Only for users with permission to add GatheringWaivers
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Submit Waiver',
            'icon' => 'bi-file-earmark-arrow-up',
            'url' => ['controller' => 'GatheringWaivers', 'action' => 'mobileSelectGathering', 'plugin' => 'Waivers'],
            'order' => 30,
            'color' => 'waivers',  // Section-specific color
            'badge' => null,
            'validRoutes' => [], // Empty = show everywhere
            'authCallback' => function ($urlParams, $user) {
                if (!$user) {
                    return false;
                }

                // Check if user can add GatheringWaivers
                $gatheringWaiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
                $tempWaiver = $gatheringWaiversTable->newEmptyEntity();

                // Use the user's checkCan method to verify add permission
                // This follows the same pattern as mobileSelectGathering controller action
                return $user->checkCan('uploadWaivers', $tempWaiver);
            }
        ];

        return $cells;
    }
}
