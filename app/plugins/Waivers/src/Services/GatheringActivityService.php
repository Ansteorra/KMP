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
 * waiver requirements. Handles associating waiver types with activities,
 * checking waiver coverage, and determining compliance status.
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
     * GatheringWaivers table instance
     *
     * @var \Waivers\Model\Table\GatheringWaiversTable
     */
    private $GatheringWaivers;

    /**
     * GatheringWaiverActivities table instance
     *
     * @var \Waivers\Model\Table\GatheringWaiverActivitiesTable
     */
    private $GatheringWaiverActivities;

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
        $this->GatheringWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringWaivers');
        $this->GatheringWaiverActivities = TableRegistry::getTableLocator()->get('Waivers.GatheringWaiverActivities');
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

    /**
     * Associate a waiver with activities it covers
     *
     * @param int $waiverId Gathering waiver ID
     * @param array $activityIds Array of gathering activity IDs
     * @return \App\Services\ServiceResult Success or failure
     */
    public function associateWaiverWithActivities(int $waiverId, array $activityIds): ServiceResult
    {
        try {
            // Check if waiver exists
            if (!$this->GatheringWaivers->exists(['id' => $waiverId])) {
                return new ServiceResult(false, 'Waiver not found');
            }

            // Remove existing associations
            $this->GatheringWaiverActivities->deleteAll(['gathering_waiver_id' => $waiverId]);

            // Create new associations
            $created = 0;
            foreach ($activityIds as $activityId) {
                // Verify activity exists
                if (!$this->GatheringActivities->exists(['id' => $activityId])) {
                    Log::warning("Skipping invalid activity ID: $activityId");
                    continue;
                }

                $association = $this->GatheringWaiverActivities->newEntity([
                    'gathering_waiver_id' => $waiverId,
                    'gathering_activity_id' => $activityId,
                ]);

                if ($this->GatheringWaiverActivities->save($association)) {
                    $created++;
                }
            }

            Log::info("Associated waiver $waiverId with $created activities");
            return new ServiceResult(true, "Waiver associated with $created activities", $created);
        } catch (\Exception $e) {
            Log::error('Error associating waiver with activities: ' . $e->getMessage());
            return new ServiceResult(false, 'Error associating waiver: ' . $e->getMessage());
        }
    }

    /**
     * Check if a waiver covers a specific activity
     *
     * @param int $waiverId Gathering waiver ID
     * @param int $activityId Gathering activity ID
     * @return \App\Services\ServiceResult Success with boolean coverage status
     */
    public function checkWaiverCoverage(int $waiverId, int $activityId): ServiceResult
    {
        try {
            $exists = $this->GatheringWaiverActivities->exists([
                'gathering_waiver_id' => $waiverId,
                'gathering_activity_id' => $activityId,
            ]);

            return new ServiceResult(true, null, $exists);
        } catch (\Exception $e) {
            Log::error('Error checking waiver coverage: ' . $e->getMessage());
            return new ServiceResult(false, 'Error checking coverage: ' . $e->getMessage());
        }
    }

    /**
     * Get all activities covered by a waiver
     *
     * @param int $waiverId Gathering waiver ID
     * @return \App\Services\ServiceResult Success with array of GatheringActivity entities
     */
    public function getCoveredActivities(int $waiverId): ServiceResult
    {
        try {
            $associations = $this->GatheringWaiverActivities->find()
                ->where(['gathering_waiver_id' => $waiverId])
                ->contain(['GatheringActivities'])
                ->all();

            $activities = [];
            foreach ($associations as $association) {
                if (isset($association->gathering_activity)) {
                    $activities[] = $association->gathering_activity;
                }
            }

            return new ServiceResult(true, null, $activities);
        } catch (\Exception $e) {
            Log::error('Error getting covered activities: ' . $e->getMessage());
            return new ServiceResult(false, 'Error retrieving activities: ' . $e->getMessage());
        }
    }

    /**
     * Get compliance status for a gathering
     *
     * Returns information about which activities have complete waiver coverage
     * and which are missing required waivers.
     *
     * @param int $gatheringId Gathering ID
     * @return \App\Services\ServiceResult Success with compliance status array
     */
    public function getGatheringComplianceStatus(int $gatheringId): ServiceResult
    {
        try {
            $activities = $this->GatheringActivities->find()
                ->where(['gathering_id' => $gatheringId])
                ->all();

            $status = [
                'compliant' => true,
                'activities' => [],
                'missing_count' => 0,
            ];

            foreach ($activities as $activity) {
                $requiredResult = $this->getRequiredWaiverTypes($activity->id);
                if (!$requiredResult->success) {
                    continue;
                }

                $required = $requiredResult->data;
                $activityStatus = [
                    'activity' => $activity,
                    'required_waiver_types' => $required,
                    'has_coverage' => count($required) === 0, // If no requirements, automatically compliant
                    'missing_types' => [],
                ];

                if (count($required) > 0) {
                    // Check if there are active waivers covering this activity
                    // Exclude declined waivers as they are not valid
                    $hasWaivers = $this->GatheringWaiverActivities->find()
                        ->where([
                            'gathering_activity_id' => $activity->id,
                        ])
                        ->contain(['GatheringWaivers' => function ($q) {
                            return $q->where([
                                'GatheringWaivers.status' => 'active',
                                'GatheringWaivers.declined_at IS' => null, // Exclude declined waivers
                            ]);
                        }])
                        ->count() > 0;

                    $activityStatus['has_coverage'] = $hasWaivers;

                    if (!$hasWaivers) {
                        $activityStatus['missing_types'] = $required;
                        $status['compliant'] = false;
                        $status['missing_count']++;
                    }
                }

                $status['activities'][] = $activityStatus;
            }

            return new ServiceResult(true, null, $status);
        } catch (\Exception $e) {
            Log::error('Error getting gathering compliance status: ' . $e->getMessage());
            return new ServiceResult(false, 'Error checking compliance: ' . $e->getMessage());
        }
    }
}
