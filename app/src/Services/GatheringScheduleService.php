<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\Gathering;
use Cake\ORM\Locator\LocatorAwareTrait;

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
     * "is_other" and "has_end_time" flag handling.
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

        // Handle "other" checkbox
        if (!empty($data['is_other'])) {
            $data['gathering_activity_id'] = null;
        }

        // Handle "has_end_time" checkbox — clear end_datetime if unchecked
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

        $data['modified_by'] = $identity->id;
        $data = $this->prepareData($data, $gathering, $identity);

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

        if ($table->delete($entity)) {
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
