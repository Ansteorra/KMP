<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringScheduledActivity Entity
 *
 * Represents a scheduled activity within a gathering with specific start/end times.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int|null $gathering_activity_id
 * @property \Cake\I18n\DateTime $start_datetime
 * @property \Cake\I18n\DateTime|null $end_datetime
 * @property bool $has_end_time
 * @property string $display_title
 * @property string|null $description
 * @property bool $pre_register
 * @property bool $is_other
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\GatheringActivity|null $gathering_activity
 * @property \App\Model\Entity\Member|null $creator
 * @property \App\Model\Entity\Member|null $modifier
 */
class GatheringScheduledActivity extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'gathering_activity_id' => true,
        'start_datetime' => true,
        'end_datetime' => true,
        'has_end_time' => true,
        'display_title' => true,
        'description' => true,
        'pre_register' => true,
        'is_other' => true,
        'created' => false,
        'modified' => false,
        'created_by' => true,
        'modified_by' => true,
        'gathering' => true,
        'gathering_activity' => true,
        'creator' => true,
        'modifier' => true,
    ];

    /**
     * Virtual field to get formatted date range
     *
     * @return string
     */
    protected function _getDateRange(): string
    {
        if (empty($this->start_datetime)) {
            return '';
        }

        // If no end time, just show the start time
        if (empty($this->end_datetime)) {
            return sprintf(
                '%s - %s',
                $this->start_datetime->format('l, M j'),
                $this->start_datetime->format('g:i A')
            );
        }

        $start = $this->start_datetime;
        $end = $this->end_datetime;

        // If same day, show: "Saturday, Nov 3 - 1:00 PM to 3:00 PM"
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return sprintf(
                '%s - %s to %s',
                $start->format('l, M j'),
                $start->format('g:i A'),
                $end->format('g:i A')
            );
        }

        // If different days, show: "Saturday, Nov 3 1:00 PM to Sunday, Nov 4 3:00 PM"
        return sprintf(
            '%s %s to %s %s',
            $start->format('l, M j'),
            $start->format('g:i A'),
            $end->format('l, M j'),
            $end->format('g:i A')
        );
    }

    /**
     * Virtual field to get activity name (from linked activity or "Other")
     *
     * @return string
     */
    protected function _getActivityName(): string
    {
        if ($this->is_other || empty($this->gathering_activity)) {
            return 'Other';
        }

        return $this->gathering_activity->name;
    }

    /**
     * Virtual field to get duration in hours
     *
     * @return float|null
     */
    protected function _getDurationHours(): ?float
    {
        if (empty($this->start_datetime) || empty($this->end_datetime)) {
            return null;
        }

        return $this->start_datetime->diffInHours($this->end_datetime, true);
    }

    /**
     * Virtual field to get display description with fallback logic
     * 
     * Priority:
     * 1. Scheduled activity's description (if set)
     * 2. Gathering activity's custom description from junction table (if exists)
     * 3. Default gathering activity description (if exists)
     *
     * @return string|null
     */
    protected function _getDisplayDescription(): ?string
    {
        // First priority: scheduled activity's own description
        if (!empty($this->description)) {
            return $this->description;
        }

        // If no gathering_activity, return null
        if (empty($this->gathering_activity)) {
            return null;
        }

        // Second priority: check for custom_description in the gathering activity
        // This would be set if we load it through the gathering's activities relationship
        if (isset($this->gathering_activity->custom_description) && !empty($this->gathering_activity->custom_description)) {
            return $this->gathering_activity->custom_description;
        }

        // Third priority: default gathering activity description
        if (!empty($this->gathering_activity->description)) {
            return $this->gathering_activity->description;
        }

        return null;
    }
}
