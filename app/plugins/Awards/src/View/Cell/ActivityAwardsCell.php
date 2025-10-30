<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

/**
 * Activity Awards View Cell
 * 
 * Provides view cell functionality for displaying awards that can be given out during
 * a specific gathering activity. This view cell implements the reverse relationship of
 * the Activities tab on Award views, showing which awards are associated with a particular
 * gathering activity.
 * 
 * The view cell integrates with the Awards plugin to display award associations for
 * gathering activities, allowing administrators to see and manage which awards can be
 * given out during specific types of activities (e.g., Armored Combat, Archery).
 * 
 * ## Display Features
 * 
 * The view cell provides comprehensive award display functionality:
 * - **Associated Awards**: List of awards that can be given during this activity
 * - **Award Details**: Display of award names, descriptions, and hierarchical information
 * - **Management Actions**: Add/remove functionality for authorized users
 * - **Empty State**: Clear messaging when no awards are associated
 * 
 * ## Permission Integration
 * 
 * The view cell implements permission-based feature access:
 * - **View Access**: Users with view permissions can see associated awards
 * - **Edit Access**: Users with edit permissions can add/remove award associations
 * - **Administrative Control**: Proper authorization checks for management operations
 * 
 * ## Usage Examples
 * 
 * ### Gathering Activity Profile Integration
 * ```php
 * // In gathering activity view template
 * echo $this->cell('Awards.ActivityAwards', [$gatheringActivity->id]);
 * ```
 * 
 * ### Tab Integration through ViewCellProvider
 * ```php
 * // Registered automatically via AwardsViewCellProvider
 * $tabs[] = [
 *     'label' => 'Awards',
 *     'content' => $this->cell('Awards.ActivityAwards', [$activityId])
 * ];
 * ```
 * 
 * @see \Awards\Controller\AwardsController Award management and association operations
 * @see \Awards\Services\AwardsViewCellProvider View cell registration and configuration
 * @see \Awards\Model\Table\AwardsTable Award data management
 */
class ActivityAwardsCell extends Cell
{
    /**
     * Prepare and expose awards data related to a gathering activity for the view.
     *
     * Loads the gathering activity, enforces view/edit permissions, fetches awards
     * associated with the activity (including domain, level, and branch relationships),
     * and computes a list of awards not yet associated for addition when the user can edit.
     *
     * Exposes template variables: `gatheringActivity`, `awards`, `canEdit`, and `availableAwards`.
     *
     * @param int $activityId The ID of the gathering activity.
     */
    public function display($activityId)
    {
        $user = $this->request->getAttribute('identity');

        // Load gathering activity (basic info for permission checking)
        $gatheringActivitiesTable = TableRegistry::getTableLocator()->get('GatheringActivities');
        $gatheringActivity = $gatheringActivitiesTable->get($activityId);

        // Check if user can view this activity
        if (!$user || !$user->can('view', $gatheringActivity)) {
            return;
        }

        // Check if user can edit (for add/remove functionality)
        $canEdit = $user->can('edit', $gatheringActivity);

        // Load awards that are associated with this gathering activity
        // We query from the Awards side since that's where the relationship exists
        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $awards = $awardsTable->find()
            ->matching('GatheringActivities', function ($q) use ($activityId) {
                return $q->where(['GatheringActivities.id' => $activityId]);
            })
            ->contain(['Domains', 'Levels', 'Branches'])
            ->all();

        // Get existing award IDs for filtering available awards
        $existingAwardIds = array_map(function ($award) {
            return $award->id;
        }, $awards->toArray());

        // Get available awards (not already associated)
        $availableAwards = [];
        if ($canEdit) {
            $availableAwards = $awardsTable->find('list')
                ->where(function ($exp) use ($existingAwardIds) {
                    if (!empty($existingAwardIds)) {
                        return $exp->notIn('id', $existingAwardIds);
                    }
                    return $exp;
                })
                ->orderBy(['name' => 'ASC'])
                ->toArray();
        }

        $this->set(compact('gatheringActivity', 'awards', 'canEdit', 'availableAwards'));
    }
}