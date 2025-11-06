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
     * Generates waiver-centric display for a gathering, showing each waiver
     * requirement as a separate row with its activity and upload status.
     * 
     * **Display Components**:
     * - Individual rows for each activity/waiver combination
     * - Upload status indicator (checkmark/count) per row
     * - Upload button for authorized users
     * - Summary statistics
     * - Links to waiver management
     * - Instructions for configuration and upload
     * 
     * **Data Loading Process**:
     * 1. Load gathering with activities
     * 2. Load all waiver requirements for each activity
     * 3. Get uploaded waiver counts by type
     * 4. Calculate upload status per waiver requirement
     * 5. Prepare waiver-centric data structure
     * 
     * **Waiver Row Structure**:
     * Creates an array where each element represents one waiver requirement:
     * - activity: GatheringActivity entity
     * - waiver_type: WaiverType entity
     * - uploaded_count: Number of waivers uploaded for this activity/type combination
     * - is_complete: Boolean indicating if at least one waiver has been uploaded
     * 
     * **Important**: Each activity/waiver combination is tracked separately. If the same
     * waiver type is required by multiple activities, each gets its own row with its own
     * upload count. A waiver uploaded for Activity A does NOT count toward Activity B,
     * even if both require the same waiver type.
     * 
     * **Data Preparation**:
     * Sets template variables:
     * - gathering: Full gathering entity
     * - gatheringId: Gathering identifier
     * - waiverRows: Array of waiver requirement rows
     * - isEmpty: Boolean indicating if gathering has any waiver requirements
     * - hasWaivers: Boolean indicating if any waivers uploaded
     * - overallStats: Overall completion statistics across all requirements
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
            $this->set('waiverRows', []);
            $this->set('isEmpty', true);
            $this->set('hasWaivers', false);
            $this->set('overallStats', ['complete' => 0, 'pending' => 0, 'total' => 0]);
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

        // Load exemption records (GatheringWaivers with is_exemption=true)
        // Exemptions are linked to activities through the join table
        // Exclude declined exemptions - they should be treated as pending
        $gatheringWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $exemptions = [];
        if (!empty($activityIds)) {
            $exemptionRecords = $gatheringWaiversTable->find()
                ->where([
                    'GatheringWaivers.is_exemption' => true,
                    'GatheringWaivers.gathering_id' => $gathering->id,
                    'GatheringWaivers.declined_at IS' => null, // Only active exemptions
                ])
                ->contain(['CreatedByMembers'])
                ->matching('GatheringWaiverActivities', function ($q) use ($activityIds) {
                    return $q->where(['GatheringWaiverActivities.gathering_activity_id IN' => $activityIds]);
                })
                ->all();

            // Build a map of [activity_id][waiver_type_id] => exemption entity
            foreach ($exemptionRecords as $exemption) {
                // Get the activity ID from the join table
                if (!empty($exemption->_matchingData['GatheringWaiverActivities'])) {
                    $activityId = $exemption->_matchingData['GatheringWaiverActivities']->gathering_activity_id;
                    $key = $activityId . '_' . $exemption->waiver_type_id;
                    $exemptions[$key] = $exemption;
                }
            }
        }

        // Build waiver-centric data structure (one row per waiver requirement)
        $waiverRows = [];
        $overallComplete = 0;
        $overallPending = 0;
        $overallExempted = 0;
        $overallTotal = 0;

        foreach ($gathering->gathering_activities as $activity) {
            // Find all waiver requirements for this activity
            foreach ($waiverRequirements as $requirement) {
                if ($requirement->gathering_activity_id === $activity->id) {
                    // Get upload count for THIS specific activity/waiver combination
                    $uploadCount = 0;
                    if (isset($activityWaiverStats[$activity->id][$requirement->waiver_type_id])) {
                        $uploadCount = $activityWaiverStats[$activity->id][$requirement->waiver_type_id];
                    }

                    // Check if there's an exemption for this combination
                    $exemptionKey = $activity->id . '_' . $requirement->waiver_type_id;
                    $exemption = $exemptions[$exemptionKey] ?? null;

                    // Create a row for this activity/waiver combination
                    $waiverRows[] = [
                        'activity' => $activity,
                        'waiver_type' => $requirement->waiver_type,
                        'uploaded_count' => $uploadCount,
                        'is_complete' => $uploadCount > 0,
                        'exemption' => $exemption
                    ];

                    // Update overall stats
                    $overallTotal++;
                    if ($exemption) {
                        $overallExempted++;
                    } elseif ($uploadCount > 0) {
                        $overallComplete++;
                    } else {
                        $overallPending++;
                    }
                }
            }
        }

        $this->set('gathering', $gathering);
        $this->set('gatheringId', $gatheringId);
        $this->set('waiverRows', $waiverRows);
        $this->set('isEmpty', empty($waiverRows));
        $this->set('hasWaivers', $hasWaivers);
        $this->set('totalWaiverCount', $totalWaiverCount);
        $this->set('declinedWaiverCount', $declinedWaiverCount);
        $this->set('overallStats', [
            'complete' => $overallComplete,
            'pending' => $overallPending,
            'exempted' => $overallExempted,
            'total' => $overallTotal
        ]);
    }
}
