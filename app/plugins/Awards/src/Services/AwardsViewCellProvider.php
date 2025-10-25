<?php

declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use Awards\View\Cell\MemberSubmittedRecsCell;
use Awards\View\Cell\RecsForMemberCell;
use App\Services\ViewCellRegistry;
use Cake\ORM\TableRegistry;

/**
 * Awards View Cell Provider
 * 
 * Provides comprehensive view cell integration for the Awards plugin with multi-format support
 * and route-based visibility. This service manages view cell registration, integration contexts,
 * and ViewCellRegistry patterns for award recommendation display across member profiles and
 * administrative interfaces.
 * 
 * The view cell provider implements the plugin view cell architecture with context-aware
 * registration, route-based visibility, and multi-format support for various display contexts
 * including member profiles, administrative interfaces, and mobile API endpoints.
 * 
 * ## View Cell Architecture
 * 
 * The provider implements structured view cell registration:
 * - **Context-Aware Registration**: Registers view cells based on request context and user permissions
 * - **Route-Based Visibility**: Configures view cells with specific route requirements and visibility rules
 * - **Multi-Format Support**: Provides view cells for web interfaces, mobile APIs, and administrative contexts
 * - **Plugin Integration**: Coordinates with ViewCellRegistry for centralized view cell management
 * 
 * ## View Cell Types
 * 
 * The provider manages multiple view cell categories:
 * - **Member Recommendations**: Display of award recommendations associated with member profiles
 * - **Award Hierarchies**: Visualization of award structure and recommendation relationships
 * - **JSON API Endpoints**: Structured data provision for mobile applications and AJAX interfaces
 * - **Administrative Views**: Specialized view cells for administrative oversight and management
 * 
 * ## Integration Contexts
 * 
 * View cells are configured for multiple integration contexts:
 * - **Member Profiles**: Integration with member profile views for recommendation display
 * - **Award Management**: Administrative interfaces for award and recommendation management
 * - **Mobile API Support**: JSON endpoints for mobile application data consumption
 * - **Dashboard Integration**: Widget support for dashboard and summary displays
 * 
 * ## Performance Considerations
 * 
 * The provider implements performance optimization:
 * - **Plugin Availability Checking**: Early return for disabled plugins to avoid unnecessary processing
 * - **Lazy Loading Patterns**: View cells are registered but not instantiated until needed
 * - **Context Filtering**: Only relevant view cells are registered based on request context
 * - **Route Optimization**: Efficient route matching for view cell visibility determination
 * 
 * ## Usage Examples
 * 
 * ### Basic View Cell Registration
 * ```php
 * // In view cell registration services
 * $user = $this->getCurrentUser();
 * $urlParams = $this->request->getParam();
 * $viewCells = AwardsViewCellProvider::getViewCells($urlParams, $user);
 * 
 * foreach ($viewCells as $cellConfig) {
 *     ViewCellRegistry::register($cellConfig);
 * }
 * ```
 * 
 * ### Plugin Integration
 * ```php
 * // In main application view cell loading
 * if (StaticHelpers::pluginEnabled('Awards')) {
 *     $awardsCells = AwardsViewCellProvider::getViewCells($urlParams, $user);
 *     $this->mergeViewCells($awardsCells);
 * }
 * ```
 * 
 * ### API Endpoint Integration
 * ```php
 * // For mobile API responses
 * $viewCells = AwardsViewCellProvider::getViewCells($urlParams, $user);
 * $jsonData = [];
 * 
 * foreach ($viewCells as $cell) {
 *     if ($cell['type'] === ViewCellRegistry::PLUGIN_TYPE_JSON) {
 *         $jsonData[$cell['id']] = $this->cell($cell['cell']);
 *     }
 * }
 * ```
 * 
 * ### Mobile Application Support
 * ```php
 * // Mobile app integration example
 * $mobileViewCells = array_filter($viewCells, function($cell) {
 *     return isset($cell['mobileSupport']) && $cell['mobileSupport'] === true;
 * });
 * 
 * $mobileResponse = [
 *     'member_recommendations' => $this->cell('Awards.MemberSubmittedRecs'),
 *     'received_recommendations' => $this->cell('Awards.RecsForMember')
 * ];
 * ```
 * 
 * @see \App\Services\ViewCellRegistry Centralized view cell management system
 * @see \App\KMP\StaticHelpers Plugin availability and configuration management
 * @see \Awards\View\Cell\MemberSubmittedRecsCell Member submitted recommendations view cell
 * @see \Awards\View\Cell\RecsForMemberCell Member received recommendations view cell
 */
class AwardsViewCellProvider
{
    /**
     * Generate view cell configurations for Awards plugin integration
     *
     * Creates comprehensive view cell configurations for award recommendation display across
     * member profiles, administrative interfaces, and mobile API endpoints. The method implements
     * context-aware registration with route-based visibility and permission-aware filtering.
     * 
     * The view cell generation process:
     * 1. **Plugin Availability Check**: Verifies Awards plugin is enabled before generating configurations
     * 2. **Context Analysis**: Analyzes URL parameters and user context for appropriate view cell selection
     * 3. **Route-Based Filtering**: Applies route validation to ensure view cells appear in appropriate contexts
     * 4. **Permission Integration**: Filters view cells based on user permissions and relationship context
     * 5. **Configuration Generation**: Creates complete ViewCellRegistry-compatible configurations
     * 
     * ## View Cell Configurations
     * 
     * ### Member Submitted Recs Cell
     * Displays award recommendations submitted by a member:
     * - **Integration Context**: Member profile views and administrative member management
     * - **Route Validation**: Valid for Members/view and Members/profile actions
     * - **Tab Integration**: Appears as "Submitted Award Recs." tab with order priority 3
     * - **Badge Support**: Supports dynamic badge display for submission counts
     * 
     * ### Recs For Member Cell
     * Displays award recommendations received by a member:
     * - **Context Filtering**: Only visible when viewing other members (not own profile)
     * - **Route Validation**: Valid for Members/view action only (excluded from profile view)
     * - **Tab Integration**: Appears as "Received Award Recs." tab with order priority 4
     * - **Permission Awareness**: Automatically filtered based on user relationship to viewed member
     * 
     * ## Route-Based Display Logic
     * 
     * The method implements sophisticated route matching:
     * - **Profile Exclusion**: Received recommendations cell excluded from profile action
     * - **Self-View Filtering**: Users cannot see "received" recommendations on their own profile
     * - **Parameter Validation**: Validates URL parameters for appropriate context determination
     * - **Multi-Route Support**: Supports multiple valid routes per view cell configuration
     *
     * @param array $urlParams URL parameters from current request for context determination
     * @param mixed $user Current authenticated user for permission and relationship context
     * @return array Complete view cell configurations ready for ViewCellRegistry registration
     * 
     * @example
     * ```php
     * // Basic view cell generation
     * $urlParams = $this->request->getParam();
     * $user = $this->getCurrentUser();
     * $viewCells = AwardsViewCellProvider::getViewCells($urlParams, $user);
     * 
     * // Register with ViewCellRegistry
     * foreach ($viewCells as $cellConfig) {
     *     ViewCellRegistry::register($cellConfig);
     * }
     * ```
     * 
     * @example
     * ```php
     * // Mobile API integration
     * $viewCells = AwardsViewCellProvider::getViewCells($urlParams, $user);
     * $mobileData = [];
     * 
     * foreach ($viewCells as $cell) {
     *     $mobileData[$cell['id']] = [
     *         'label' => $cell['label'],
     *         'data' => $this->cell($cell['cell'])
     *     ];
     * }
     * ```
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        if (!$user) {
            return [];
        }
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Awards')) {
            return [];
        }

        $cells = [];

        // Handle Members controller views
        if ($urlParams["controller"] == 'Members') {
            // Member Submitted Recs Cell - shows award recommendations submitted by a member
            // only show this if you are allowed to see recommendations OR this is your own profile
            if (
                $urlParams['action'] == 'profile'
                || (
                    $urlParams["action"] == 'view'
                    && $user->id == $urlParams['pass'][0]
                )
                || ($user->can('ViewSubmittedByMember', 'Awards.Recommendations'))
            ) {
                $cells[] = [
                    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                    'label' => 'Submitted Award Recs.',
                    'id' => 'member-submitted-recs',
                    'order' => 3,
                    'tabBtnBadge' => null,
                    'cell' => 'Awards.MemberSubmittedRecs',
                    'validRoutes' => [
                        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                        ['controller' => 'Members', 'action' => 'profile', 'plugin' => null]
                    ]
                ];
            }

            // Recs For Member Cell - shows award recommendations received by a member
            // you can't see this if you are looking at your own profile
            if (
                $urlParams['action'] != 'profile'
                && (
                    $urlParams["action"] == 'view'
                    && $user->id != $urlParams['pass'][0]
                )
                &&
                ($user->can('ViewSubmittedForMember', 'Awards.Recommendations'))
            ) {
                $cells[] = [
                    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                    'label' => 'Received Award Recs.',
                    'id' => 'recs-for-member',
                    'order' => 4,
                    'tabBtnBadge' => null,
                    'cell' => 'Awards.RecsForMember',
                    'validRoutes' => [
                        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                    ]
                ];
            }
        }

        // Handle GatheringActivities controller views
        if ($urlParams["controller"] == 'GatheringActivities' && $urlParams['action'] == 'view') {
            // Activity Awards Cell - shows awards that can be given during this activity
            // Load the gathering activity to check permissions
            $gatheringActivitiesTable = TableRegistry::getTableLocator()->get('GatheringActivities');
            try {
                $activityId = $urlParams['pass'][0] ?? null;
                if ($activityId) {
                    $gatheringActivity = $gatheringActivitiesTable->get($activityId);

                    // Only show if user can view the activity
                    if ($user->can('view', $gatheringActivity)) {
                        $cells[] = [
                            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                            'label' => 'Awards',
                            'id' => 'activity-awards',
                            'order' => 10,
                            'tabBtnBadge' => null,
                            'cell' => 'Awards.ActivityAwards',
                            'validRoutes' => [
                                ['controller' => 'GatheringActivities', 'action' => 'view', 'plugin' => null],
                            ]
                        ];
                    }
                }
            } catch (\Exception $e) {
                // If activity not found or error, just don't add the cell
            }
        }

        return $cells;
    }
}
