<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;

/**
 * Gathering Awards View Cell
 * 
 * Displays award recommendations associated with a specific gathering in the gathering detail view.
 * This view cell integrates with the Awards plugin's recommendation table system to show
 * recommendations that have been linked to a gathering, providing visibility into awards
 * planned for ceremony at that gathering.
 * 
 * **Purpose**: 
 * - Display award recommendations filtered by gathering_id
 * - Integration point for Awards tab in gathering detail pages
 * - Provide quick access to gathering-specific award information
 * 
 * **Core Responsibilities**:
 * - Permission Validation - Checks user has ViewGatheringRecommendations permission
 * - Data Availability Check - Determines if gathering has any recommendations
 * - Turbo Frame Integration - Lazy loads recommendation table via turbo-frame
 * - Empty State Handling - Shows appropriate message when no recommendations exist
 * 
 * **Architecture**:
 * This view cell extends CakePHP Cell to provide reusable recommendation display
 * functionality for gathering views. It integrates with the ViewCellRegistry for
 * automatic tab injection in the gathering detail interface.
 * 
 * **Display Features**:
 * - **Lazy Loading**: Uses turbo-frame with lazy loading for performance
 * - **Permission-Based Access**: Respects ViewGatheringRecommendations permission
 * - **Empty State**: Helpful message when no recommendations associated
 * - **Table View Integration**: Uses existing recommendation table view with Event config
 * 
 * **Gathering Context Support**:
 * - **Gathering View Integration**: Embedded as tab in gathering detail pages
 * - **Recommendation Filtering**: Shows only recommendations linked to this gathering
 * - **Authorization**: Checks permissions against the gathering entity
 * 
 * **Integration Points**:
 * - GatheringsTable - Gathering entity data for permission checks
 * - RecommendationsTable - Recommendation counting and existence checks
 * - ViewCellRegistry - Automatic tab injection system
 * - RecommendationsController::Table - Table view rendering
 * 
 * **Data Loading Logic**:
 * 1. Load gathering entity by ID
 * 2. Check user has ViewGatheringRecommendations permission
 * 3. Count recommendations associated with gathering
 * 4. Set isEmpty flag for template rendering
 * 5. Pass gathering ID to template for URL building
 * 
 * **Performance Considerations**:
 * - Lazy loading via turbo-frame defers table rendering
 * - Simple count query to check for recommendations
 * - No eager loading of recommendation data in cell
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Automatic via ViewCellRegistry in gathering view
 * echo $this->cell('Awards.GatheringAwards', [$gatheringId]);
 * ```
 * 
 * **Template Integration**:
 * Renders tab interface with:
 * - Turbo-frame with lazy loading to recommendation table
 * - URL pointing to Table action with gathering_id filter
 * - Empty state message when no recommendations
 * 
 * **Security Features**:
 * - Permission check via ViewGatheringRecommendations
 * - Secure gathering entity loading
 * - User-specific authorization validation
 * 
 * **Error Handling**:
 * - Graceful handling of missing gathering
 * - Empty state for gatherings without recommendations
 * - Permission denial handling (no display if unauthorized)
 * 
 * @see GatheringAwardsViewCellProvider View cell registration
 * @see ViewCellRegistry Automatic tab injection system
 * @see RecommendationsController::Table Table view rendering
 */
class GatheringAwardsCell extends Cell
{
    /**
     * Display Gathering Awards Interface
     *
     * Generates award recommendations display for a gathering, showing recommendations
     * associated with this gathering via the recommendation table view in a lazy-loaded
     * turbo-frame.
     * 
     * **Display Components**:
     * - Turbo-frame with lazy loading
     * - Link to recommendation table with gathering_id filter
     * - Empty state message when no recommendations
     * 
     * **Data Loading Process**:
     * 1. Load gathering entity from database
     * 2. Get current user from request
     * 3. Check ViewGatheringRecommendations permission
     * 4. Count recommendations for this gathering
     * 5. Set template variables for rendering
     * 
     * **Permission Check**:
     * Uses the ViewGatheringRecommendations permission to determine if the user
     * can view recommendations associated with gatherings. If permission is denied,
     * the cell returns early without rendering.
     * 
     * **Data Preparation**:
     * Sets template variables:
     * - gatheringId: Gathering integer ID for database queries
     * - isEmpty: Boolean indicating if gathering has any recommendations
     * 
     * Note: User is accessed in the template via $this->getRequest()->getAttribute('identity')
     * 
     * @param int $gatheringId Gathering integer ID (not public_id)
     * @param string|null $model Optional model name (for compatibility with view cell pattern, not used)
     * @return void
     */
    public function display(int $gatheringId, ?string $model = null): void
    {
        // Get current user
        $currentUser = $this->request->getAttribute('identity');

        // Load gathering entity for permission check
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        try {
            $gathering = $gatheringsTable->get($gatheringId);
        } catch (\Exception $e) {
            // If gathering not found, don't display the cell
            \Cake\Log\Log::error('GatheringAwardsCell: Gathering not found: ' . $e->getMessage());
            return;
        }

        // Check if user has permission to view gathering recommendations
        $canView = $currentUser->can('ViewGatheringRecommendations', 'Awards.Recommendations', $gathering);
        \Cake\Log\Log::debug('GatheringAwardsCell: canView = ' . ($canView ? 'true' : 'false'));
        \Cake\Log\Log::debug('GatheringAwardsCell: isSuperUser = ' . ($currentUser->isSuperUser() ? 'true' : 'false'));

        if (!$canView) {
            \Cake\Log\Log::debug('GatheringAwardsCell: Permission check failed for gathering ' . $gathering->id);
            return;
        }

        // Check if there are any recommendations for this gathering
        $recommendationsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $isEmpty = $recommendationsTable->find()
            ->where(['gathering_id' => $gathering->id])
            ->count() === 0;

        $this->set('gatheringId', $gathering->id);
        $this->set('isEmpty', $isEmpty);
    }
}
