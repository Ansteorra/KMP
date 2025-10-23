<?php

declare(strict_types=1);

namespace App\Service;

use App\Services\ServiceResult;
use Cake\ORM\TableRegistry;

/**
 * GatheringActivityService
 *
 * Service class for gathering activity business logic including
 * waiver consolidation and activity management.
 */
class GatheringActivityService
{
    /**
     * Get required waivers for a list of activities
     *
     * Consolidates waivers from multiple activities - if multiple activities
     * require the same waiver, it only appears once in the result.
     *
     * @param array<int> $activityIds List of gathering activity IDs
     * @return \App\Services\ServiceResult Result with consolidated waiver list
     */
    public function getRequiredWaivers(array $activityIds): ServiceResult
    {
        if (empty($activityIds)) {
            return new ServiceResult(true, null, []);
        }

        $GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $waivers = $GatheringActivityWaivers->find()
            ->where(['gathering_activity_id IN' => $activityIds])
            ->contain(['WaiverTypes'])
            ->all();

        // Consolidate waivers - use waiver_type_id as key to eliminate duplicates
        $consolidatedWaivers = [];
        foreach ($waivers as $gaw) {
            if ($gaw->waiver_type && !isset($consolidatedWaivers[$gaw->waiver_type_id])) {
                $consolidatedWaivers[$gaw->waiver_type_id] = [
                    'id' => $gaw->waiver_type->id,
                    'name' => $gaw->waiver_type->name,
                    'description' => $gaw->waiver_type->description,
                    'retention_policy' => $gaw->waiver_type->retention_policy,
                ];
            }
        }

        // Sort by name
        usort($consolidatedWaivers, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return new ServiceResult(true, null, array_values($consolidatedWaivers));
    }

    /**
     * Check if an activity can be modified
     *
     * Activities cannot be modified once waivers have been uploaded for
     * a gathering using that activity.
     *
     * @param int $activityId Gathering activity ID
     * @return \App\Services\ServiceResult Result with boolean data
     */
    public function canModifyActivity(int $activityId): ServiceResult
    {
        $GatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->get($activityId);

        // Template activities (gathering_id = null) can always be modified
        if ($activity->gathering_id === null) {
            return new ServiceResult(true, null, true);
        }

        // TODO: Check if gathering has uploaded waivers (Phase 5)
        // For now, gathering activities can be modified
        return new ServiceResult(true, null, true);
    }

    /**
     * Check if an activity can be deleted
     *
     * Template activities can be deleted unless they are currently being
     * used by a gathering.
     *
     * @param int $activityId Gathering activity ID
     * @return \App\Services\ServiceResult Result with boolean data
     */
    public function canDeleteActivity(int $activityId): ServiceResult
    {
        $GatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->get($activityId);

        // If this is a gathering-specific activity (not a template), check gathering status
        if ($activity->gathering_id !== null) {
            // TODO: Check gathering status (Phase 5)
            return new ServiceResult(true, null, false);
        }

        // Template activities can be deleted if not in use
        // TODO: Check if template is being used by any gathering (Phase 5)
        return new ServiceResult(true, null, true);
    }

    /**
     * Get activity summary with waiver count
     *
     * @param int $activityId Gathering activity ID
     * @return \App\Services\ServiceResult Result with activity summary data
     */
    public function getActivitySummary(int $activityId): ServiceResult
    {
        $GatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->get($activityId, contain: [
            'GatheringActivityWaivers',
        ]);

        $summary = [
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'waiver_count' => count($activity->gathering_activity_waivers),
        ];

        return new ServiceResult(true, null, $summary);
    }
}
