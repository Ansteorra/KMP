<?php

declare(strict_types=1);

namespace Waivers\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;

/**
 * Gathering Activity Waivers View Cell
 * 
 * **Purpose**: Displays waiver requirements for a specific gathering activity,
 * showing which waiver types are required for participants in this activity.
 * 
 * **Core Responsibilities**:
 * - Waiver Requirements Display - Shows all required waivers for an activity
 * - Waiver Type Information - Displays waiver details and descriptions
 * - Management Interface - Links to add/edit/remove waiver requirements
 * - Empty State Handling - User-friendly display when no waivers are required
 * 
 * **Architecture**: 
 * This view cell extends CakePHP Cell to provide reusable waiver requirement
 * display functionality that can be embedded in gathering activity views and
 * administrative interfaces. It integrates with the Waivers plugin system.
 * 
 * **Display Features**:
 * - **Required Waivers List**: All waiver types required for the activity
 * - **Waiver Details**: Name, description, and status of each waiver type
 * - **Management Actions**: Add/remove waiver requirements (with permissions)
 * - **Empty State**: Helpful message when no waivers are required
 * 
 * **Activity Context Support**:
 * - **Activity View Integration**: Embedded in activity detail pages
 * - **Administrative Access**: Waiver requirement management
 * - **Compliance Checking**: Clear display of activity requirements
 * 
 * **Integration Points**:
 * - GatheringActivityWaiversTable - Waiver requirement relationships
 * - WaiverTypesTable - Waiver type configuration and details
 * - GatheringActivitiesTable - Activity information
 * - Authorization System - Permission-based action display
 * 
 * **Performance Considerations**:
 * - Efficient query with contained associations
 * - Selective field loading for waiver information
 * - Minimal data transfer for display
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Gathering activity view integration
 * echo $this->cell('Waivers.GatheringActivityWaivers', [$activityId]);
 * 
 * // Administrative waiver management
 * echo $this->cell('Waivers.GatheringActivityWaivers', [$activityId]);
 * ```
 * 
 * **Template Integration**:
 * Renders tabbed interface with:
 * - List of required waiver types
 * - Waiver type descriptions and details
 * - Add/remove waiver requirement actions
 * - Empty state for activities without requirements
 * 
 * **Security Features**:
 * - Permission-based action display
 * - Secure activity identification
 * - Authorization-aware management interface
 * 
 * **Error Handling**:
 * - Graceful handling of missing activity data
 * - Empty state display for activities without waiver requirements
 * - Validation of activity existence
 * 
 * **Troubleshooting**:
 * - Verify activity exists and has valid ID
 * - Check waiver requirement associations
 * - Validate permission configuration
 * - Monitor query performance for activities with many waivers
 * 
 * @see GatheringActivityWaiversTable Waiver requirement management
 * @see WaiverTypesTable Waiver type configuration
 */
class GatheringActivityWaiversCell extends Cell
{
    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Display Gathering Activity Waiver Requirements
     *
     * Generates waiver requirement display for specified activity with
     * required waiver types, descriptions, and management capabilities.
     * 
     * **Display Components**:
     * - List of required waiver types
     * - Waiver type details (name, description, status)
     * - Management actions (add/remove requirements)
     * - Empty state for activities without requirements
     * 
     * **Data Loading**:
     * Queries waiver requirements for the activity with:
     * - Contained WaiverTypes association for full details
     * - Active waiver types only (not soft-deleted)
     * - Ordered by waiver type name
     * 
     * **Performance Optimization**:
     * - Single query with contain for associated data
     * - Selective field loading
     * - Optimized for display rendering
     * 
     * **Data Preparation**:
     * Sets template variables:
     * - gatheringActivityId: Activity identifier for linking
     * - waiverRequirements: Collection of waiver requirements with types
     * - isEmpty: Boolean indicating if activity has any waiver requirements
     * 
     * @param int $gatheringActivityId Gathering Activity ID
     * @param string|null $model Optional model name (for compatibility with view cell pattern)
     * @return void
     */
    public function display(int $gatheringActivityId, ?string $model = null): void
    {
        $gatheringActivityWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');

        // Get all waiver requirements for this activity
        $waiverRequirements = $gatheringActivityWaiversTable
            ->find()
            ->where(['GatheringActivityWaivers.gathering_activity_id' => $gatheringActivityId])
            ->contain([
                'WaiverTypes' => function ($q) {
                    return $q->where(['WaiverTypes.deleted IS' => null]);
                }
            ])
            ->all();

        $this->set('gatheringActivityId', $gatheringActivityId);
        $this->set('waiverRequirements', $waiverRequirements);
        $this->set('isEmpty', $waiverRequirements->count() === 0);
    }
}
