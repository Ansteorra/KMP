<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\ServiceResult;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;

/**
 * Gathering Activity Service
 *
 * Manages business logic for gathering activities and their relationship to
 * waiver requirements. Handles associating waiver types with activities.
 *
 * @see /docs/5.7-waivers-plugin.md
 */
class GatheringActivityService
{
    /**
     * GatheringActivities table instance
     *
     * @var \App\Model\Table\GatheringActivitiesTable
     */
    private $GatheringActivities;

    /**
     * GatheringActivityWaivers table instance
     *
     * @var \Waivers\Model\Table\GatheringActivityWaiversTable
     */
    private $GatheringActivityWaivers;

    /**
     * WaiverTypes table instance
     *
     * @var \Waivers\Model\Table\WaiverTypesTable
     */
    private $WaiverTypes;

    /**
     * Constructor - initializes table instances
     */
    public function __construct()
    {
        $this->GatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $this->GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $this->WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
    }

    /**
     * Add waiver requirement to an activity
     *
     * @param int $activityId Gathering activity ID
     * @param int $waiverTypeId Waiver type ID
     * @return \App\Services\ServiceResult Success or failure
     */
    public function addWaiverRequirement(int $activityId, int $waiverTypeId): ServiceResult
    {
        try {
            // Check if activity exists
            if (!$this->GatheringActivities->exists(['id' => $activityId])) {
                return new ServiceResult(false, 'Gathering activity not found');
            }

            // Check if waiver type exists
            if (!$this->WaiverTypes->exists(['id' => $waiverTypeId])) {
                return new ServiceResult(false, 'Waiver type not found');
            }

            // Check if requirement already exists
            $exists = $this->GatheringActivityWaivers->exists([
                'gathering_activity_id' => $activityId,
                'waiver_type_id' => $waiverTypeId,
            ]);

            if ($exists) {
                return new ServiceResult(false, 'This waiver requirement already exists for this activity');
            }

            // Create the requirement
            $requirement = $this->GatheringActivityWaivers->newEntity([
                'gathering_activity_id' => $activityId,
                'waiver_type_id' => $waiverTypeId,
            ]);

            if ($this->GatheringActivityWaivers->save($requirement)) {
                Log::info("Added waiver requirement: activity=$activityId, waiver_type=$waiverTypeId");
                return new ServiceResult(true, 'Waiver requirement added successfully');
            }

            return new ServiceResult(false, 'Failed to save waiver requirement');
        } catch (\Exception $e) {
            Log::error('Error adding waiver requirement: ' . $e->getMessage());
            return new ServiceResult(false, 'Error adding waiver requirement: ' . $e->getMessage());
        }
    }

    /**
     * Remove waiver requirement from an activity
     *
     * @param int $activityId Gathering activity ID
     * @param int $waiverTypeId Waiver type ID
     * @return \App\Services\ServiceResult Success or failure
     */
    public function removeWaiverRequirement(int $activityId, int $waiverTypeId): ServiceResult
    {
        try {
            $requirement = $this->GatheringActivityWaivers->find()
                ->where([
                    'gathering_activity_id' => $activityId,
                    'waiver_type_id' => $waiverTypeId,
                ])
                ->first();

            if (!$requirement) {
                return new ServiceResult(false, 'Waiver requirement not found');
            }

            if ($this->GatheringActivityWaivers->delete($requirement)) {
                Log::info("Removed waiver requirement: activity=$activityId, waiver_type=$waiverTypeId");
                return new ServiceResult(true, 'Waiver requirement removed successfully');
            }

            return new ServiceResult(false, 'Failed to remove waiver requirement');
        } catch (\Exception $e) {
            Log::error('Error removing waiver requirement: ' . $e->getMessage());
            return new ServiceResult(false, 'Error removing waiver requirement: ' . $e->getMessage());
        }
    }

    /**
     * Get required waiver types for an activity
     *
     * @param int $activityId Gathering activity ID
     * @return \App\Services\ServiceResult Success with array of WaiverType entities
     */
    public function getRequiredWaiverTypes(int $activityId): ServiceResult
    {
        try {
            $requirements = $this->GatheringActivityWaivers->find()
                ->where(['gathering_activity_id' => $activityId])
                ->contain(['WaiverTypes'])
                ->all();

            $waiverTypes = [];
            foreach ($requirements as $requirement) {
                if (isset($requirement->waiver_type)) {
                    $waiverTypes[] = $requirement->waiver_type;
                }
            }

            return new ServiceResult(true, null, $waiverTypes);
        } catch (\Exception $e) {
            Log::error('Error getting required waiver types: ' . $e->getMessage());
            return new ServiceResult(false, 'Error retrieving waiver requirements: ' . $e->getMessage());
        }
    }
}
