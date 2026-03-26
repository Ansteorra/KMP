<?php
declare(strict_types=1);

namespace App\Services;

use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Manages gathering-activity associations: linking, unlinking, and description updates.
 *
 * Handles the join table between gatherings and gathering_activities, including
 * waiver-lock checks that prevent modifications when waivers have been uploaded.
 */
class GatheringActivityService
{
    use LocatorAwareTrait;

    /**
     * Check whether the gathering has uploaded waivers that lock activity changes.
     *
     * @param int $gatheringId Gathering id
     * @return bool True if waivers exist and activities are locked
     */
    public function hasWaiverLock(int $gatheringId): bool
    {
        // TODO: Implement when Waivers plugin is available
        // return $this->fetchTable('Waivers.GatheringWaivers')
        //     ->find()->where(['gathering_id' => $gatheringId])->count() > 0;
        return false;
    }

    /**
     * Link an activity to a gathering.
     *
     * @param int $gatheringId Gathering id
     * @param int $activityId Activity id to add
     * @param array $existingIds Already-linked activity ids
     * @param string|null $customDescription Optional custom description
     * @return array{success: bool, message: string, type: string}
     */
    public function addActivity(
        int $gatheringId,
        int $activityId,
        array $existingIds,
        ?string $customDescription = null,
    ): array {
        // Check if activity is already linked
        if (in_array($activityId, $existingIds)) {
            return [
                'success' => false,
                'message' => __('This activity is already part of this gathering.'),
                'type' => 'warning',
            ];
        }

        $table = $this->fetchTable('GatheringsGatheringActivities');

        $linkData = [
            'gathering_id' => $gatheringId,
            'gathering_activity_id' => $activityId,
            'sort_order' => 999,
        ];

        if ($customDescription !== null && trim($customDescription) !== '') {
            $linkData['custom_description'] = trim($customDescription);
        }

        $link = $table->newEntity($linkData);

        if ($table->save($link)) {
            return [
                'success' => true,
                'message' => __('Activity added successfully.'),
                'type' => 'success',
            ];
        }

        return [
            'success' => false,
            'message' => __('Unable to add activity. Please try again.'),
            'type' => 'error',
        ];
    }

    /**
     * Remove an activity from a gathering.
     *
     * @param int $gatheringId Gathering id
     * @param int $activityId Activity id to remove
     * @return array{success: bool, message: string, type: string}
     */
    public function removeActivity(int $gatheringId, int $activityId): array
    {
        $table = $this->fetchTable('GatheringsGatheringActivities');
        $link = $table->find()
            ->where([
                'gathering_id' => $gatheringId,
                'gathering_activity_id' => $activityId,
            ])
            ->first();

        if (!$link) {
            return [
                'success' => false,
                'message' => __('Activity link not found.'),
                'type' => 'error',
            ];
        }

        if ($table->delete($link)) {
            return [
                'success' => true,
                'message' => __('Activity removed successfully.'),
                'type' => 'success',
            ];
        }

        return [
            'success' => false,
            'message' => __('Unable to remove activity. Please try again.'),
            'type' => 'error',
        ];
    }

    /**
     * Update the custom description on a gathering-activity link.
     *
     * @param int $gatheringId Gathering id
     * @param int $activityId Activity id
     * @param string|null $customDescription New description (null/empty to clear)
     * @return array{success: bool, message: string, type: string}
     */
    public function editDescription(
        int $gatheringId,
        int $activityId,
        ?string $customDescription,
    ): array {
        $table = $this->fetchTable('GatheringsGatheringActivities');
        $link = $table->find()
            ->where([
                'gathering_id' => $gatheringId,
                'gathering_activity_id' => $activityId,
            ])
            ->first();

        if (!$link) {
            return [
                'success' => false,
                'message' => __('Activity link not found.'),
                'type' => 'error',
            ];
        }

        $link->custom_description = $customDescription !== null && trim($customDescription) !== ''
            ? trim($customDescription)
            : null;

        if ($table->save($link)) {
            return [
                'success' => true,
                'message' => __('Activity description updated successfully.'),
                'type' => 'success',
            ];
        }

        $errors = $link->getErrors();
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = $error;
                }
            }

            return [
                'success' => false,
                'message' => __(
                    'Unable to update activity description: {0}',
                    implode(', ', $errorMessages),
                ),
                'type' => 'error',
            ];
        }

        return [
            'success' => false,
            'message' => __('Unable to update activity description. Please try again.'),
            'type' => 'error',
        ];
    }
}
