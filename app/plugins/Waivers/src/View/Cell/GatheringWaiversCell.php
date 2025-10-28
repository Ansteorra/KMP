<?php

declare(strict_types=1);

namespace Waivers\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;

/**
 * Gathering Waivers View Cell
 * 
 * **Purpose**: Displays comprehensive waiver information for a gathering, combining
 * waiver requirements from activities with upload interface and management functionality.
 * This is a merged view that consolidates what were previously separate "Required Waivers"
 * and "Waivers" tabs into a single cohesive interface.
 * 
 * **Core Responsibilities**:
 * - Requirement Aggregation - Shows all waiver types required across activities
 * - Upload Statistics - Displays counts of uploaded waivers by type
 * - Upload Interface - Provides access to waiver upload functionality
 * - Waiver Management - Links to waiver listing and management interfaces
 * - Status Tracking - Shows collection status and activity locking state
 * - Activity Mapping - Shows which activities require each waiver type
 * 
 * **Architecture**: 
 * This view cell extends CakePHP Cell to provide reusable waiver management
 * functionality for gathering views. It integrates with the Waivers plugin system
 * and the ViewCellRegistry for automatic tab injection.
 * 
 * **Display Features**:
 * - **Activity-Centric Table**: Shows activities with their required waivers
 * - **Completion Status**: Visual indicators (progress bars/badges) for each activity
 * - **Upload Indicators**: Color-coded badges showing which waivers have been uploaded
 * - **Overall Summary**: Top-level statistics showing complete vs pending waivers
 * - **Upload Button**: Quick access to waiver upload interface (when authorized)
 * - **Waiver Management Links**: Navigation to full waiver listing and management
 * - **Activity Links**: Direct links to each activity for configuration
 * - **Empty States**: Helpful guidance when no waivers required or uploaded
 * - **Instructions**: Step-by-step upload and configuration instructions
 * 
 * **Gathering Context Support**:
 * - **Gathering View Integration**: Embedded as tab in gathering detail pages
 * - **Activity Locking**: Shows when activities are locked due to waivers
 * - **Authorization**: Respects user permissions for upload and management
 * 
 * **Integration Points**:
 * - GatheringsTable - Gathering entity data
 * - GatheringWaiversTable - Uploaded waiver statistics
 * - GatheringActivityWaiversTable - Required waiver types
 * - WaiverTypesTable - Waiver type details
 * - ViewCellRegistry - Automatic tab injection
 * 
 * **Data Loading Logic**:
 * 1. Load gathering with activities
 * 2. Check if any waivers have been uploaded
 * 3. Calculate statistics by waiver type
 * 4. Determine required waiver types from activities
 * 5. Prepare display data
 * 
 * **Performance Considerations**:
 * - Efficient queries with selective field loading
 * - Single query for statistics aggregation
 * - Cached waiver type lookups
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Gathering view integration (automatic via ViewCellRegistry)
 * echo $this->cell('Waivers.GatheringWaivers', [$gatheringId]);
 * ```
 * 
 * **Template Integration**:
 * Renders tab interface with:
 * - Upload button for authorized users
 * - Statistics cards for each required waiver type
 * - Links to waiver listing and management
 * - Status alerts and instructions
 * - Empty states for no requirements or uploads
 * 
 * **Security Features**:
 * - Authorization checks for upload and management actions
 * - Secure gathering identification
 * - User-specific permission handling
 * 
 * **Error Handling**:
 * - Graceful handling of missing gathering data
 * - Empty state for gatherings without activities
 * - Empty state for activities without waiver requirements
 * 
 * **Troubleshooting**:
 * - Verify gathering exists and has valid ID
 * - Check waiver table associations are correct
 * - Validate activity waiver requirements are configured
 * - Monitor query performance for gatherings with many waivers
 * 
 * @see GatheringRequiredWaiversCell Activity-level waiver requirements display
 * @see WaiversViewCellProvider View cell registration
 * @see ViewCellRegistry Automatic tab injection system
 */
class GatheringWaiversCell extends Cell
{
    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Display Gathering Waivers Interface
     *
     * Generates activity-centric waiver display for a gathering, pivoted to show
     * which activities require which waivers and their upload completion status.
     * 
     * **Display Components**:
     * - Activity-based view of waiver requirements
     * - Upload completion status per activity
     * - Upload button for authorized users
     * - Summary statistics
     * - Links to waiver management
     * - Instructions for configuration and upload
     * 
     * **Data Loading Process**:
     * 1. Load gathering with activities
     * 2. Load all waiver requirements for each activity
     * 3. Get uploaded waiver counts by type
     * 4. Calculate completion status per activity
     * 5. Prepare activity-centric data structure
     * 
     * **Activity Aggregation Logic**:
     * Creates an array pivoted by activity where:
     * - Key: activity_id
     * - Value: [
     *     'activity' => GatheringActivity entity,
     *     'required_waivers' => [array of:
     *         'waiver_type' => WaiverType entity,
     *         'uploaded_count' => count of waivers uploaded FOR THIS SPECIFIC ACTIVITY
     *     ],
     *     'completion_status' => [
     *         'complete' => count of waiver types with uploads for this activity,
     *         'pending' => count of waiver types without uploads for this activity,
     *         'total' => total required waiver types
     *     ]
     *   ]
     * 
     * **Important**: Waivers are tracked per activity. If two activities require the same
     * waiver type, they are tracked separately. A waiver uploaded and associated with
     * Activity A will NOT count as complete for Activity B, even if both require the
     * same waiver type.
     * 
     * **Data Preparation**:
     * Sets template variables:
     * - gathering: Full gathering entity
     * - gatheringId: Gathering identifier
     * - activitiesWithWaivers: Activity-centric data with activity-specific completion status
     * - isEmpty: Boolean indicating if gathering has any waiver requirements
     * - hasWaivers: Boolean indicating if any waivers uploaded
     * - overallStats: Overall completion statistics across all activities
     * 
     * Note: User is accessed in the template via $this->getRequest()->getAttribute('identity')
     * 
     * @param int $gatheringId Gathering ID
     * @param string|null $model Optional model name (for compatibility with view cell pattern)
     * @return void
     */
    public function display(int $gatheringId, ?string $model = null): void
    {
        // Load gathering with activities
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->get($gatheringId, [
            'contain' => ['GatheringActivities' => ['sort' => ['GatheringActivities.name' => 'ASC']], 'GatheringTypes']
        ]);

        // If no activities, show empty state
        if (empty($gathering->gathering_activities)) {
            $this->set('gathering', $gathering);
            $this->set('gatheringId', $gatheringId);
            $this->set('activitiesWithWaivers', []);
            $this->set('isEmpty', true);
            $this->set('hasWaivers', false);
            $this->set('overallStats', ['complete' => 0, 'pending' => 0, 'total' => 0]);
            $this->set('waiverStats', []);
            return;
        }

        // Get all activity IDs
        $activityIds = array_column($gathering->gathering_activities, 'id');

        // Load all waiver requirements for these activities
        $gatheringActivityWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $waiverRequirements = $gatheringActivityWaiversTable
            ->find()
            ->where(['GatheringActivityWaivers.gathering_activity_id IN' => $activityIds])
            ->contain([
                'WaiverTypes' => function ($q) {
                    return $q->where(['WaiverTypes.deleted IS' => null]);
                },
                'GatheringActivities'
            ])
            ->all();

        // Check if waivers have been uploaded (excluding declined waivers)
        $gatheringWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $hasWaivers = $gatheringWaiversTable->find()
            ->where([
                'gathering_id' => $gatheringId,
                'declined_at IS' => null, // Exclude declined waivers
            ])
            ->count() > 0;

        // Get total waiver count (including declined)
        $totalWaiverCount = $gatheringWaiversTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->count();

        // Get declined waiver count
        $declinedWaiverCount = $gatheringWaiversTable->find()
            ->where([
                'gathering_id' => $gatheringId,
                'declined_at IS NOT' => null, // Only declined waivers
            ])
            ->count();

        // Get waiver upload statistics by activity and type
        // We need to check GatheringWaiverActivities to see which activities each waiver is for
        $gatheringWaiverActivitiesTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverActivities');
        $activityWaiverStats = [];
        if ($hasWaivers && !empty($activityIds)) {
            // Load all waiver-activity associations for activities in this gathering
            // Exclude declined waivers from statistics
            $waiverActivityLinks = $gatheringWaiverActivitiesTable->find()
                ->contain(['GatheringWaivers' => function ($q) use ($gatheringId) {
                    return $q->where([
                        'GatheringWaivers.gathering_id' => $gatheringId,
                        'GatheringWaivers.declined_at IS' => null, // Exclude declined waivers
                    ]);
                }])
                ->where(['GatheringWaiverActivities.gathering_activity_id IN' => $activityIds])
                ->all();

            // Build a map of [activity_id][waiver_type_id] => count
            foreach ($waiverActivityLinks as $link) {
                if ($link->gathering_waiver) {
                    $activityId = $link->gathering_activity_id;
                    $waiverTypeId = $link->gathering_waiver->waiver_type_id;

                    if (!isset($activityWaiverStats[$activityId])) {
                        $activityWaiverStats[$activityId] = [];
                    }
                    if (!isset($activityWaiverStats[$activityId][$waiverTypeId])) {
                        $activityWaiverStats[$activityId][$waiverTypeId] = 0;
                    }
                    $activityWaiverStats[$activityId][$waiverTypeId]++;
                }
            }
        }

        // Build activity-centric data structure
        $activitiesWithWaivers = [];
        $overallComplete = 0;
        $overallPending = 0;
        $overallTotal = 0;

        foreach ($gathering->gathering_activities as $activity) {
            // Find all waiver requirements for this activity
            $activityWaivers = [];
            foreach ($waiverRequirements as $requirement) {
                if ($requirement->gathering_activity_id === $activity->id) {
                    // Add waiver type with upload count for THIS specific activity
                    $uploadCount = 0;
                    if (isset($activityWaiverStats[$activity->id][$requirement->waiver_type_id])) {
                        $uploadCount = $activityWaiverStats[$activity->id][$requirement->waiver_type_id];
                    }

                    $activityWaivers[] = [
                        'waiver_type' => $requirement->waiver_type,
                        'uploaded_count' => $uploadCount
                    ];
                }
            }

            // Only include activities that have waiver requirements
            if (!empty($activityWaivers)) {
                // Calculate completion status for this activity
                $complete = 0;
                $pending = 0;

                foreach ($activityWaivers as $waiverData) {
                    if ($waiverData['uploaded_count'] > 0) {
                        $complete++;
                    } else {
                        $pending++;
                    }
                }

                $activitiesWithWaivers[$activity->id] = [
                    'activity' => $activity,
                    'required_waivers' => $activityWaivers,
                    'completion_status' => [
                        'complete' => $complete,
                        'pending' => $pending,
                        'total' => count($activityWaivers)
                    ]
                ];

                // Update overall stats
                $overallComplete += $complete;
                $overallPending += $pending;
                $overallTotal += count($activityWaivers);
            }
        }

        $this->set('gathering', $gathering);
        $this->set('gatheringId', $gatheringId);
        $this->set('activitiesWithWaivers', $activitiesWithWaivers);
        $this->set('isEmpty', empty($activitiesWithWaivers));
        $this->set('hasWaivers', $hasWaivers);
        $this->set('totalWaiverCount', $totalWaiverCount);
        $this->set('declinedWaiverCount', $declinedWaiverCount);
        $this->set('overallStats', [
            'complete' => $overallComplete,
            'pending' => $overallPending,
            'total' => $overallTotal
        ]);
    }
}
