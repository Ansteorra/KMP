<?php
declare(strict_types=1);

namespace App\Services;

use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Manages gathering-activity associations: linking, unlinking, and description updates.
 *
 * Handles the join table between gatherings and gathering_activities.
 */
class GatheringActivityService
{
    use LocatorAwareTrait;

    /**
     * Check whether the gathering already has uploaded waivers.
     *
     * @param int $gatheringId Gathering id
     * @return bool True if waivers exist for the gathering
     */
    public function hasUploadedWaivers(int $gatheringId): bool
    {
        if (!class_exists('Waivers\Model\Table\GatheringWaiversTable')) {
            return false;
        }

        return $this->fetchTable('Waivers.GatheringWaivers')
            ->find()
            ->where(['gathering_id' => $gatheringId])
            ->count() > 0;
    }

    /**
     * Link an activity to a gathering.
     *
     * @param int $gatheringId Gathering id
     * @param int $activityId Activity id to add
     * @param array $existingIds Already-linked activity ids
     * @param string|null $customDescription Optional custom description
     * @param int|null $actorId Actual operator; null is reserved for trusted internal callers.
     * @return array{success: bool, message: string, type: string}
     */
    public function addActivity(
        int $gatheringId,
        int $activityId,
        array $existingIds,
        ?string $customDescription = null,
        ?int $actorId = null,
    ): array {
        return $this->withActivityAuthority(
            $gatheringId,
            $activityId,
            $actorId,
            fn(): array => $this->linkActivity($gatheringId, $activityId, $existingIds, $customDescription),
        );
    }

    /** Persist the attachment while the caller holds current activity authority. */
    private function linkActivity(
        int $gatheringId,
        int $activityId,
        array $existingIds,
        ?string $customDescription,
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
     * @param int|null $actorId Actual operator; null is reserved for trusted internal callers.
     * @return array{success: bool, message: string, type: string}
     */
    public function removeActivity(int $gatheringId, int $activityId, ?int $actorId = null): array
    {
        return $this->withActivityAuthority(
            $gatheringId,
            $activityId,
            $actorId,
            fn(): array => $this->unlinkActivity($gatheringId, $activityId),
        );
    }

    /** Remove an attachment only after its schedule dependencies are gone. */
    private function unlinkActivity(int $gatheringId, int $activityId): array
    {
        if (
            $this->fetchTable('GatheringScheduledActivities')->exists([
                'gathering_id' => $gatheringId, 'gathering_activity_id' => $activityId,
            ])
        ) {
            return ['success' => false, 'type' => 'error',
                'message' => __('Remove this activity’s schedule entries before removing the activity.')];
        }
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
     * @param int|null $actorId Actual operator; null is reserved for trusted internal callers.
     * @return array{success: bool, message: string, type: string}
     */
    public function editDescription(
        int $gatheringId,
        int $activityId,
        ?string $customDescription,
        ?int $actorId = null,
    ): array {
        return $this->withActivityAuthority(
            $gatheringId,
            $activityId,
            $actorId,
            fn(): array => $this->updateDescription($gatheringId, $activityId, $customDescription),
        );
    }

    /** Update descriptive text while holding the current event grant. */
    private function updateDescription(int $gatheringId, int $activityId, ?string $customDescription): array
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

    /** An actor's grant is held until the entire mutation commits. */
    private function withActivityAuthority(int $gatheringId, int $activityId, ?int $actorId, callable $operation): array
    {
        $result = [];
        $this->fetchTable('GatheringsGatheringActivities')->getConnection()->transactional(
            function () use ($gatheringId, $activityId, $actorId, $operation, &$result): bool {
                if ($actorId !== null) {
                    (new GatheringActivityAuthorizationService())->assertActivity($gatheringId, $activityId, $actorId);
                }

                $result = $operation();

                return (bool)$result['success'];
            },
        );

        return $result;
    }
}
