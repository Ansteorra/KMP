<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\Gathering;
use App\Services\ActionItems\ActionItemService;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;

/**
 * Manages scheduled activity CRUD for gatherings.
 *
 * Handles creation, editing, and deletion of GatheringScheduledActivity records
 * with timezone conversion from user/gathering timezone to UTC.
 */
class GatheringScheduleService
{
    use LocatorAwareTrait;

    /**
     * Prepare request data for a scheduled activity: timezone conversion,
     * duration selection and activity flags.
     *
     * @param array $data Raw request data
     * @param \App\Model\Entity\Gathering $gathering The parent gathering
     * @param mixed $identity Authenticated user identity
     * @return array Processed data ready for entity patching
     */
    public function prepareData(array $data, Gathering $gathering, mixed $identity): array
    {
        $timezone = TimezoneHelper::getGatheringTimezone($gathering, $identity);

        if (!empty($data['start_datetime'])) {
            $data['start_datetime'] = TimezoneHelper::toUtc($data['start_datetime'], $timezone);
        }
        if (!empty($data['end_datetime'])) {
            $data['end_datetime'] = TimezoneHelper::toUtc($data['end_datetime'], $timezone);
        }

        if (isset($data['duration_minutes']) && $data['duration_minutes'] !== 'existing') {
            $data['has_end_time'] = $data['duration_minutes'] !== 'other';
            $data['end_datetime'] = $data['has_end_time'] && !empty($data['start_datetime'])
                ? $data['start_datetime']->addMinutes((int)$data['duration_minutes'])
                : null;
        }

        // Handle "other" checkbox
        if (!empty($data['is_other'])) {
            $data['gathering_activity_id'] = null;
        }

        // Open-ended entries have no stored end time.
        if (empty($data['has_end_time'])) {
            $data['end_datetime'] = null;
        }

        return $data;
    }

    /**
     * Create a new scheduled activity for a gathering.
     *
     * @param array $data Request data (already containing gathering_id and created_by)
     * @param \App\Model\Entity\Gathering $gathering The parent gathering
     * @param mixed $identity Authenticated user identity
     * @return array{success: bool, message: string, data?: \App\Model\Entity\GatheringScheduledActivity, errors?: array}
     */
    public function add(array $data, Gathering $gathering, mixed $identity): array
    {
        if (!$this->validDuration($data)) {
            return ['success' => false, 'message' => __('Choose a duration from 15 minutes to 4 hours, or Other.')];
        }
        $data['gathering_id'] = $gathering->id;
        $data['created_by'] = $identity->id;

        $data = $this->prepareData($data, $gathering, $identity);

        $table = $this->fetchTable('GatheringScheduledActivities');
        $entity = $table->newEmptyEntity();
        $entity = $table->patchEntity($entity, $data);

        if ($table->save($entity)) {
            return [
                'success' => true,
                'message' => __('Scheduled activity added successfully.'),
                'data' => $entity,
            ];
        }

        return [
            'success' => false,
            'message' => __('Could not add scheduled activity.'),
            'errors' => $this->flattenErrors($entity->getErrors()),
        ];
    }

    /**
     * Update an existing scheduled activity.
     *
     * @param int $scheduledActivityId Scheduled activity id
     * @param array $data Request data
     * @param \App\Model\Entity\Gathering $gathering The parent gathering
     * @param mixed $identity Authenticated user identity
     * @return array{success: bool, message: string, data?: \App\Model\Entity\GatheringScheduledActivity, errors?: array, invalidOwner?: bool}
     */
    public function edit(
        int $scheduledActivityId,
        array $data,
        Gathering $gathering,
        mixed $identity,
    ): array {
        $table = $this->fetchTable('GatheringScheduledActivities');
        $entity = $table->get($scheduledActivityId);

        // Verify ownership
        if ($entity->gathering_id != $gathering->id) {
            return [
                'success' => false,
                'message' => __('Invalid scheduled activity.'),
                'invalidOwner' => true,
            ];
        }

        if (!$this->validDuration($data, true)) {
            return ['success' => false, 'message' => __('Choose a duration from 15 minutes to 4 hours, or Other.')];
        }
        $data['modified_by'] = $identity->id;
        $data = $this->prepareData($data, $gathering, $identity);
        if (($data['duration_minutes'] ?? null) === 'existing') {
            $data['end_datetime'] = $entity->end_datetime;
            $data['has_end_time'] = $entity->has_end_time;
        }

        $entity = $table->patchEntity($entity, $data);

        if ($table->save($entity)) {
            return [
                'success' => true,
                'message' => __('Scheduled activity updated successfully.'),
                'data' => $entity,
            ];
        }

        return [
            'success' => false,
            'message' => __('Could not update scheduled activity.'),
            'errors' => $this->flattenErrors($entity->getErrors()),
        ];
    }

    /**
     * Accept the duration picker while retaining compatibility with existing datetime clients.
     *
     * @param array $data Submitted fields.
     * @param bool $editing Whether an existing end time can be retained.
     * @return bool
     */
    private function validDuration(array $data, bool $editing = false): bool
    {
        if (!array_key_exists('duration_minutes', $data)) {
            return true;
        }
        $allowed = array_map('strval', range(15, 240, 15));
        $allowed[] = 'other';
        if ($editing) {
            $allowed[] = 'existing';
        }

        return is_scalar($data['duration_minutes'])
            && in_array((string)$data['duration_minutes'], $allowed, true);
    }

    /**
     * Delete a scheduled activity, verifying it belongs to the given gathering.
     *
     * @param int $scheduledActivityId Scheduled activity id
     * @param \App\Model\Entity\Gathering $gathering The parent gathering
     * @return array{success: bool, message: string, invalidOwner?: bool}
     */
    public function delete(int $scheduledActivityId, Gathering $gathering): array
    {
        $table = $this->fetchTable('GatheringScheduledActivities');
        $entity = $table->get($scheduledActivityId);

        if ($entity->gathering_id != $gathering->id) {
            return [
                'success' => false,
                'message' => __('Invalid scheduled activity.'),
                'invalidOwner' => true,
            ];
        }

        $deleted = $table->getConnection()->transactional(
            function () use ($table, $entity, $scheduledActivityId): bool {
                $this->releaseCourtAssignments($scheduledActivityId);

                return (bool)$table->delete($entity);
            },
        );

        if ($deleted) {
            return [
                'success' => true,
                'message' => __('Scheduled activity deleted successfully.'),
            ];
        }

        return [
            'success' => false,
            'message' => __('Could not delete scheduled activity. Please try again.'),
        ];
    }

    /**
     * Clear award court assignments that depend on a scheduled activity being deleted.
     *
     * @param int $scheduledActivityId Scheduled gathering activity ID.
     * @return void
     */
    private function releaseCourtAssignments(int $scheduledActivityId): void
    {
        $bestowals = $this->fetchTable('Awards.Bestowals');
        $affectedRows = $bestowals->find()
            ->select(['id'])
            ->where(['gathering_scheduled_activity_id' => $scheduledActivityId])
            ->enableHydration(false)
            ->all();
        $affectedBestowalIds = [];
        foreach ($affectedRows as $row) {
            $affectedBestowalIds[] = (int)$row['id'];
        }

        $bestowals->updateAll(
            [
                'gathering_scheduled_activity_id' => null,
                'roaming_court' => false,
            ],
            ['gathering_scheduled_activity_id' => $scheduledActivityId],
        );
        $actionItemService = new ActionItemService();
        foreach ($affectedBestowalIds as $bestowalId) {
            $syncResult = $actionItemService->syncRequiredFieldCompletionStates(
                Bestowal::ACTION_ITEM_ENTITY_TYPE,
                $bestowalId,
            );
            if (!$syncResult->success) {
                throw new RuntimeException((string)$syncResult->reason);
            }
        }

        $segments = $this->fetchTable('Awards.CourtAgendaSegments');
        $linkedSegments = $segments->find()
            ->where(['gathering_scheduled_activity_id' => $scheduledActivityId])
            ->all();
        foreach ($linkedSegments as $segment) {
            $segments->deleteOrFail($segment);
        }
    }

    /**
     * Flatten CakePHP nested validation errors into a simple string array.
     *
     * @param array $errors Nested error array from entity
     * @return array<string>
     */
    private function flattenErrors(array $errors): array
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = is_string($error) ? $error : implode(', ', $error);
            }
        }

        return $messages;
    }
}
