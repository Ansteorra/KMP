<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Gathering;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Deep-copies a gathering including its activities, staff, and schedule items.
 *
 * Handles date-offset calculation so that cloned schedule items are shifted to
 * align with the new gathering's start date.
 */
class GatheringCloneService
{
    use LocatorAwareTrait;

    /**
     * Clone activities from the source gathering into the new one,
     * then back-fill any required template activities that are missing.
     *
     * @param \App\Model\Entity\Gathering $original Source gathering (with gathering_activities loaded)
     * @param \App\Model\Entity\Gathering $newGathering Newly saved gathering
     * @return int Number of activities cloned
     */
    public function cloneActivities(Gathering $original, Gathering $newGathering): int
    {
        $table = $this->fetchTable('GatheringsGatheringActivities');
        $cloned = 0;
        $clonedActivityIds = [];

        // Copy activities from source gathering
        foreach ($original->gathering_activities as $activity) {
            $link = $table->newEntity([
                'gathering_id' => $newGathering->id,
                'gathering_activity_id' => $activity->id,
                'sort_order' => $activity->_joinData->sort_order ?? 999,
                'custom_description' => $activity->_joinData->custom_description ?? null,
            ]);

            if ($table->save($link)) {
                $cloned++;
                $clonedActivityIds[$activity->id] = true;
            }
        }

        // Back-fill required template activities missing from source
        $templateActivities = $this->fetchTable('GatheringTypeGatheringActivities')->find()
            ->where([
                'gathering_type_id' => $newGathering->gathering_type_id,
                'not_removable' => true,
            ])
            ->all();

        $maxSort = 0;
        foreach ($original->gathering_activities as $a) {
            $order = $a->_joinData->sort_order ?? 0;
            if ($order > $maxSort) {
                $maxSort = $order;
            }
        }

        foreach ($templateActivities as $tmpl) {
            if (isset($clonedActivityIds[$tmpl->gathering_activity_id])) {
                // Already cloned — ensure not_removable flag is set
                $existing = $table->find()
                    ->where([
                        'gathering_id' => $newGathering->id,
                        'gathering_activity_id' => $tmpl->gathering_activity_id,
                    ])
                    ->first();
                if ($existing && !$existing->not_removable) {
                    $existing->not_removable = true;
                    $table->save($existing);
                }
                continue;
            }

            $maxSort++;
            $link = $table->newEntity([
                'gathering_id' => $newGathering->id,
                'gathering_activity_id' => $tmpl->gathering_activity_id,
                'sort_order' => $maxSort,
            ]);
            $link->not_removable = true;
            if ($table->save($link)) {
                $cloned++;
            }
        }

        return $cloned;
    }

    /**
     * Clone staff members from the source gathering.
     *
     * @param \App\Model\Entity\Gathering $original Source gathering (with gathering_staff loaded)
     * @param \App\Model\Entity\Gathering $newGathering Newly saved gathering
     * @return int Number of staff members cloned
     */
    public function cloneStaff(Gathering $original, Gathering $newGathering): int
    {
        $table = $this->fetchTable('GatheringStaff');
        $cloned = 0;

        foreach ($original->gathering_staff as $staff) {
            $newStaff = $table->newEntity([
                'gathering_id' => $newGathering->id,
                'member_id' => $staff->member_id,
                'sca_name' => $staff->sca_name,
                'role' => $staff->role,
                'is_steward' => $staff->is_steward,
                'show_on_public_page' => $staff->show_on_public_page,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'contact_notes' => $staff->contact_notes,
                'sort_order' => $staff->sort_order,
            ]);

            if ($table->save($newStaff)) {
                $cloned++;
            }
        }

        return $cloned;
    }

    /**
     * Clone scheduled activities, shifting times by the offset between
     * the original and new gathering start dates.
     *
     * @param \App\Model\Entity\Gathering $original Source gathering (with gathering_scheduled_activities loaded)
     * @param \App\Model\Entity\Gathering $newGathering Newly saved gathering
     * @return int Number of scheduled activities cloned
     */
    public function cloneSchedule(Gathering $original, Gathering $newGathering): int
    {
        $table = $this->fetchTable('GatheringScheduledActivities');
        $cloned = 0;

        $timeDiff = $newGathering->start_date->getTimestamp() - $original->start_date->getTimestamp();

        foreach ($original->gathering_scheduled_activities as $scheduledActivity) {
            $newStartDateTime = clone $scheduledActivity->start_datetime;
            $newStartDateTime = $newStartDateTime->modify(sprintf('%+d seconds', $timeDiff));

            $newEndDateTime = null;
            if ($scheduledActivity->end_datetime) {
                $newEndDateTime = clone $scheduledActivity->end_datetime;
                $newEndDateTime = $newEndDateTime->modify(sprintf('%+d seconds', $timeDiff));
            }

            $newEntity = $table->newEntity([
                'gathering_id' => $newGathering->id,
                'gathering_activity_id' => $scheduledActivity->gathering_activity_id,
                'start_datetime' => $newStartDateTime,
                'end_datetime' => $newEndDateTime,
                'has_end_time' => !empty($scheduledActivity->end_datetime),
                'display_title' => $scheduledActivity->display_title,
                'description' => $scheduledActivity->description,
                'pre_register' => $scheduledActivity->pre_register ?? false,
                'is_other' => $scheduledActivity->is_other ?? false,
            ]);

            if ($table->save($newEntity)) {
                $cloned++;
            } else {
                $errors = $newEntity->getErrors();
                Log::error('Failed to clone scheduled activity: ' . json_encode($errors));
            }
        }

        return $cloned;
    }

    /**
     * Build a human-readable success message summarising what was cloned.
     *
     * @param string $gatheringName New gathering name
     * @param int $activities Number of cloned activities
     * @param int $staff Number of cloned staff
     * @param int $schedule Number of cloned schedule items
     * @return string Formatted success message
     */
    public function buildSuccessMessage(
        string $gatheringName,
        int $activities,
        int $staff,
        int $schedule,
    ): string {
        $parts = [];
        if ($activities > 0) {
            $parts[] = __('{0} {1}', $activities, __n('activity', 'activities', $activities));
        }
        if ($staff > 0) {
            $parts[] = __('{0} {1}', $staff, __n('staff member', 'staff members', $staff));
        }
        if ($schedule > 0) {
            $parts[] = __(
                '{0} {1}',
                $schedule,
                __n('scheduled activity', 'scheduled activities', $schedule),
            );
        }

        if (!empty($parts)) {
            return (string)__(
                'Gathering "{0}" has been cloned successfully with {1}.',
                $gatheringName,
                implode(', ', $parts),
            );
        }

        return (string)__(
            'Gathering "{0}" has been cloned successfully.',
            $gatheringName,
        );
    }
}
