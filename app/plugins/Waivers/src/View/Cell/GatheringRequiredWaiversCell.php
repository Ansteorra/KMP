<?php

declare(strict_types=1);

namespace Waivers\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;

/**
 * Gathering Required Waivers View Cell
 * 
 * **Purpose**: Displays aggregated waiver requirements for a gathering by analyzing
 * all activities associated with the gathering and presenting a consolidated view
 * of required waivers.
 * 
 * **Core Responsibilities**:
 * - Waiver Aggregation - Collects waiver requirements from all gathering activities
 * - Deduplication - Shows each unique waiver type only once
 * - Activity Tracking - Displays which activities require each waiver
 * - Compliance Overview - Provides clear view of all waiver requirements
 * 
 * **Architecture**: 
 * This view cell extends CakePHP Cell to provide reusable waiver requirement
 * aggregation functionality for gathering views. It integrates with the Waivers
 * plugin system and the ViewCellRegistry for automatic tab injection.
 * 
 * **Display Features**:
 * - **Aggregated Waivers List**: All unique waiver types required across activities
 * - **Activity References**: Shows which activities require each waiver
 * - **Waiver Details**: Name, description, retention period, template links
 * - **Empty State**: Helpful message when no waivers are required
 * 
 * **Gathering Context Support**:
 * - **Gathering View Integration**: Embedded as tab in gathering detail pages
 * - **Multi-Activity Analysis**: Aggregates requirements across all activities
 * - **Compliance Planning**: Clear overview for gathering organizers
 * 
 * **Integration Points**:
 * - GatheringsTable - Gathering entity with activities
 * - GatheringActivitiesTable - Activity information
 * - GatheringActivityWaiversTable - Activity waiver requirements
 * - WaiverTypesTable - Waiver type details
 * - ViewCellRegistry - Automatic tab injection
 * 
 * **Data Aggregation Logic**:
 * 1. Load all activities for the gathering
 * 2. Load all waiver requirements for each activity
 * 3. Deduplicate by waiver_type_id
 * 4. Track which activities require each waiver
 * 5. Order by waiver type name
 * 
 * **Performance Considerations**:
 * - Efficient query with contained associations
 * - Single query to load activities and requirements
 * - In-memory deduplication and aggregation
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Gathering view integration (automatic via ViewCellRegistry)
 * echo $this->cell('Waivers.GatheringRequiredWaivers', [$gatheringId]);
 * ```
 * 
 * **Template Integration**:
 * Renders tab interface with:
 * - Table of unique required waiver types
 * - Activity references for each waiver
 * - Waiver details and template links
 * - Empty state for gatherings without waiver requirements
 * 
 * **Security Features**:
 * - Read-only display (no management actions)
 * - Secure gathering identification
 * - Activity-level waiver management (via GatheringActivityWaivers cell)
 * 
 * **Error Handling**:
 * - Graceful handling of missing gathering data
 * - Empty state for gatherings without activities
 * - Empty state for activities without waivers
 * 
 * **Troubleshooting**:
 * - Verify gathering exists and has valid ID
 * - Check activity associations are loaded
 * - Validate waiver requirement associations
 * - Monitor query performance for gatherings with many activities
 * 
 * @see GatheringActivityWaiversCell Activity-level waiver management
 * @see WaiversViewCellProvider View cell registration
 * @see ViewCellRegistry Automatic tab injection system
 */
class GatheringRequiredWaiversCell extends Cell
{
    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Display Gathering Required Waivers
     *
     * Generates aggregated waiver requirement display for a gathering by
     * analyzing all associated activities and their waiver requirements.
     * 
     * **Display Components**:
     * - Aggregated list of unique waiver types
     * - Activity references for each waiver
     * - Waiver details (name, description, template, retention)
     * - Empty state for gatherings without requirements
     * 
     * **Data Loading Process**:
     * 1. Load gathering with activities
     * 2. For each activity, load waiver requirements with waiver types
     * 3. Aggregate and deduplicate by waiver_type_id
     * 4. Track which activities require each waiver
     * 5. Order by waiver type name
     * 
     * **Aggregation Logic**:
     * Creates a consolidated array where:
     * - Key: waiver_type_id
     * - Value: [
     *     'waiver_type' => WaiverType entity,
     *     'activities' => [array of GatheringActivity entities]
     *   ]
     * 
     * **Performance Optimization**:
     * - Uses contain to load associations in single query
     * - In-memory aggregation for efficiency
     * - Selective field loading
     * 
     * **Data Preparation**:
     * Sets template variables:
     * - gatheringId: Gathering identifier for linking
     * - aggregatedWaivers: Consolidated array of waiver requirements
     * - isEmpty: Boolean indicating if gathering has any waiver requirements
     * - totalWaivers: Count of unique waiver types required
     * - totalActivities: Count of activities with waiver requirements
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
            'contain' => ['GatheringActivities']
        ]);

        // If no activities, show empty state
        if (empty($gathering->gathering_activities)) {
            $this->set('gatheringId', $gatheringId);
            $this->set('aggregatedWaivers', []);
            $this->set('isEmpty', true);
            $this->set('totalWaivers', 0);
            $this->set('totalActivities', 0);
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

        // Aggregate waivers by waiver_type_id
        $aggregatedWaivers = [];
        foreach ($waiverRequirements as $requirement) {
            $waiverTypeId = $requirement->waiver_type_id;

            if (!isset($aggregatedWaivers[$waiverTypeId])) {
                $aggregatedWaivers[$waiverTypeId] = [
                    'waiver_type' => $requirement->waiver_type,
                    'activities' => []
                ];
            }

            // Add activity to the list if not already there
            $activityExists = false;
            foreach ($aggregatedWaivers[$waiverTypeId]['activities'] as $existingActivity) {
                if ($existingActivity->id === $requirement->gathering_activity->id) {
                    $activityExists = true;
                    break;
                }
            }

            if (!$activityExists) {
                $aggregatedWaivers[$waiverTypeId]['activities'][] = $requirement->gathering_activity;
            }
        }

        // Sort by waiver type name
        uasort($aggregatedWaivers, function ($a, $b) {
            return strcmp($a['waiver_type']->name, $b['waiver_type']->name);
        });

        $this->set('gatheringId', $gatheringId);
        $this->set('aggregatedWaivers', $aggregatedWaivers);
        $this->set('isEmpty', empty($aggregatedWaivers));
        $this->set('totalWaivers', count($aggregatedWaivers));
        $this->set('totalActivities', count(array_filter($gathering->gathering_activities, function ($activity) use ($activityIds, $waiverRequirements) {
            // Count activities that have at least one waiver requirement
            foreach ($waiverRequirements as $req) {
                if ($req->gathering_activity_id === $activity->id) {
                    return true;
                }
            }
            return false;
        })));
    }
}
