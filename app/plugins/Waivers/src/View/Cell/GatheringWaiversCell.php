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
 * - Requirement Aggregation - Shows all waiver types required for the gathering
 * - Upload Statistics - Displays counts of uploaded waivers by type
 * - Upload Interface - Provides access to waiver upload functionality
 * - Waiver Management - Links to waiver listing and management interfaces
 * - Status Tracking - Shows collection status
 * 
 * **Architecture**: 
 * This view cell extends CakePHP Cell to provide reusable waiver management
 * functionality for gathering views. It integrates with the Waivers plugin system
 * and the ViewCellRegistry for automatic tab injection.
 * 
 * **Display Features**:
 * - **Gathering-Level Table**: Shows each required waiver type for the gathering
 * - **Completion Status**: Visual indicators (progress bars/badges) for each waiver type
 * - **Upload Indicators**: Color-coded badges showing which waivers have been uploaded
 * - **Overall Summary**: Top-level statistics showing complete vs pending waivers
 * - **Upload Button**: Quick access to waiver upload interface (when authorized)
 * - **Waiver Management Links**: Navigation to full waiver listing and management
 * - **Empty States**: Helpful guidance when no waivers required or uploaded
 * - **Instructions**: Step-by-step upload and configuration instructions
 * 
 * **Gathering Context Support**:
 * - **Gathering View Integration**: Embedded as tab in gathering detail pages
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
     * requirement as a separate row with its upload status.
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
     * 2. Load all waiver requirements across the gathering
     * 3. Get uploaded waiver counts by type
     * 4. Calculate upload status per waiver requirement
     * 5. Prepare waiver-centric data structure
     * 
     * **Waiver Row Structure**:
     * Creates an array where each element represents one waiver requirement:
     * - waiver_type: WaiverType entity
     * - uploaded_count: Number of waivers uploaded for this waiver type
     * - is_complete: Boolean indicating if at least one waiver has been uploaded or attested
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
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->get($gatheringId, [
            'contain' => ['GatheringActivities' => ['sort' => ['GatheringActivities.name' => 'ASC']], 'GatheringTypes']
        ]);

        $gatheringActivityWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $gatheringWaiversTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $gatheringWaiverClosuresTable = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverClosures');
        $waiverTypesTable = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');

        $activityIds = array_column($gathering->gathering_activities ?? [], 'id');

        $waiverClosure = $gatheringWaiverClosuresTable->getClosureForGathering($gatheringId);
        $waiverCollectionClosed = $waiverClosure !== null;
        $canCloseWaivers = false;
        $user = $this->request->getAttribute('identity');
        if ($user) {
            $tempWaiver = $gatheringWaiversTable->newEmptyEntity();
            $tempWaiver->gathering = $gathering;
            $canCloseWaivers = $user->checkCan('closeWaivers', $tempWaiver);
        }

        $requiredWaiverTypeIds = [];
        if (!empty($activityIds)) {
            $requiredWaiverTypeIds = $gatheringActivityWaiversTable
                ->find()
                ->where(['GatheringActivityWaivers.gathering_activity_id IN' => $activityIds])
                ->distinct(['GatheringActivityWaivers.waiver_type_id'])
                ->all()
                ->extract('waiver_type_id')
                ->toArray();
        }

        $waiverTypes = [];
        if (!empty($requiredWaiverTypeIds)) {
            $result = $waiverTypesTable
                ->find()
                ->where([
                    'WaiverTypes.id IN' => $requiredWaiverTypeIds,
                    'WaiverTypes.deleted IS' => null,
                ])
                ->orderBy(['WaiverTypes.name' => 'ASC'])
                ->all();

            foreach ($result as $waiverType) {
                $waiverTypes[$waiverType->id] = $waiverType;
            }
        }

        $waiversByType = $gatheringWaiversTable
            ->find()
            ->where([
                'GatheringWaivers.gathering_id' => $gatheringId,
                'GatheringWaivers.declined_at IS' => null,
            ])
            ->contain(['CreatedByMembers'])
            ->all()
            ->groupBy('waiver_type_id')
            ->toArray();

        $hasWaivers = array_sum(array_map('count', $waiversByType)) > 0;

        $totalWaiverCount = $gatheringWaiversTable->find()
            ->where(['gathering_id' => $gatheringId])
            ->count();

        $declinedWaiverCount = $gatheringWaiversTable->find()
            ->where([
                'gathering_id' => $gatheringId,
                'declined_at IS NOT' => null,
            ])
            ->count();

        $waiverRows = [];
        $overallComplete = 0;
        $overallPending = 0;
        $overallExempted = 0;
        $overallTotal = 0;

        foreach ($waiverTypes as $waiverTypeId => $waiverType) {
            $waiversForType = $waiversByType[$waiverTypeId] ?? [];

            $uploadedCount = count(array_filter($waiversForType, function ($waiver) {
                return !$waiver->is_exemption;
            }));

            $exemption = null;
            foreach ($waiversForType as $waiver) {
                if ($waiver->is_exemption) {
                    $exemption = $waiver;
                    break;
                }
            }

            $waiverRows[] = [
                'waiver_type' => $waiverType,
                'uploaded_count' => $uploadedCount,
                'is_complete' => ($uploadedCount > 0 || (bool)$exemption),
                'exemption' => $exemption,
            ];

            $overallTotal++;
            if ($exemption) {
                $overallExempted++;
            } elseif ($uploadedCount > 0) {
                $overallComplete++;
            } else {
                $overallPending++;
            }
        }

        $this->set('gathering', $gathering);
        $this->set('gatheringId', $gatheringId);
        $this->set('waiverRows', $waiverRows);
        $this->set('isEmpty', empty($waiverRows));
        $this->set('hasWaivers', $hasWaivers);
        $this->set('totalWaiverCount', $totalWaiverCount);
        $this->set('declinedWaiverCount', $declinedWaiverCount);
        $this->set('waiverClosure', $waiverClosure);
        $this->set('waiverCollectionClosed', $waiverCollectionClosed);
        $this->set('canCloseWaivers', $canCloseWaivers);
        $this->set('overallStats', [
            'complete' => $overallComplete,
            'pending' => $overallPending,
            'exempted' => $overallExempted,
            'total' => $overallTotal,
        ]);
    }
}
