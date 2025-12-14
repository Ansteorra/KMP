<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

/**
 * Displays awards that can be given during a specific gathering activity.
 * 
 * Shows the reverse relationship of the Activities tab on Award views,
 * allowing administrators to see and manage award-activity associations.
 * 
 * @see \Awards\Services\AwardsViewCellProvider View cell registration
 * @see /docs/5.2.17-awards-services.md Full documentation
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
