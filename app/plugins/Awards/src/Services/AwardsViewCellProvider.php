<?php

declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use Awards\View\Cell\MemberSubmittedRecsCell;
use Awards\View\Cell\RecsForMemberCell;
use App\Services\ViewCellRegistry;
use Cake\ORM\TableRegistry;

/**
 * Provides view cell integration for the Awards plugin.
 * 
 * Manages view cell registration for member profiles, gathering activities,
 * and gathering views with route-based visibility and permission checking.
 * 
 * @see \App\Services\ViewCellRegistry Centralized view cell management
 * @see \Awards\View\Cell\MemberSubmittedRecsCell Member submitted recommendations
 * @see \Awards\View\Cell\RecsForMemberCell Member received recommendations
 * @see /docs/5.2.17-awards-services.md Full documentation
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
