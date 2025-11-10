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
     * Build view cell configurations for the Awards plugin based on request context and user permissions.
     *
     * Generates an array of ViewCellRegistry-compatible configuration arrays for integration points
     * such as member profile tabs and gathering activity views. Configurations are included only when
     * the Awards plugin is enabled, the request context (controller/action/route parameters) matches
     * a supported integration, and the current user has the required permission or relationship to view
     * the underlying data.
     *
     * @param array $urlParams Request URL parameters used to determine controller, action, and route context.
     * @param mixed $user Current authenticated user used for permission and relationship checks.
     * @return array An array of view cell configuration arrays ready for registration with ViewCellRegistry.
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

        // Handle Gatherings controller views
        if ($urlParams["controller"] == 'Gatherings' && $urlParams['action'] == 'view') {
            // Gathering Awards Cell - shows award recommendations associated with this gathering
            // Load the gathering to check permissions
            // NOTE: GatheringsController overrides $recordId to use integer ID, not public_id
            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            try {
                // Get public_id from URL parameters
                $publicId = $urlParams['pass'][0] ?? null;
                if ($publicId) {
                    // Look up gathering by public_id to get integer ID and check permissions
                    $gathering = $gatheringsTable->find()
                        ->where(['public_id' => $publicId])
                        ->firstOrFail();

                    // Only show if user has ViewGatheringRecommendations permission
                    if ($user->can('ViewGatheringRecommendations', 'Awards.Recommendations', $gathering)) {
                        $cells[] = [
                            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                            'label' => 'Award Recommendations',
                            'id' => 'gathering-awards',
                            'order' => 8,
                            'tabBtnBadge' => null,
                            'cell' => 'Awards.GatheringAwards',
                            'validRoutes' => [
                                ['controller' => 'Gatherings', 'action' => 'view', 'plugin' => null],
                            ]
                        ];
                    }
                }
            } catch (\Exception $e) {
                // If gathering not found or error, just don't add the cell
                \Cake\Log\Log::error('Awards: Failed to add gathering awards cell: ' . $e->getMessage());
                \Cake\Log\Log::error('Awards: Exception trace: ' . $e->getTraceAsString());
            }
        }

        return $cells;
    }
}
