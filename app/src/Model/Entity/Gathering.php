<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Gathering Entity
 *
 * Represents an SCA gathering (tournament, practice, feast, etc.).
 * Gatherings have activities and can require waivers.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $gathering_type_id
 * @property string $name
 * @property string|null $description
 * @property \Cake\I18n\DateTime $start_date
 * @property \Cake\I18n\DateTime $end_date
 * @property string|null $location
 * @property string|null $timezone
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $public_page_enabled
 * @property int $created_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \Cake\I18n\DateTime|null $cancelled_at
 * @property string|null $cancellation_reason
 *
 * @property \App\Model\Entity\Branch $branch
 * @property \App\Model\Entity\GatheringType $gathering_type
 * @property \App\Model\Entity\Member $creator
 * @property \App\Model\Entity\GatheringActivity[] $gathering_activities
 * @property \App\Model\Entity\GatheringAttendance[] $gathering_attendances
 * @property \App\Model\Entity\GatheringScheduledActivity[] $gathering_scheduled_activities
 * @property \App\Model\Entity\GatheringStaff[] $gathering_staff
 * @property \Waivers\Model\Entity\GatheringWaiver[] $gathering_waivers
 */
class Gathering extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'branch_id' => true,
        'gathering_type_id' => true,
        'name' => true,
        'description' => true,
        'start_date' => true,
        'end_date' => true,
        'location' => true,
        'timezone' => true,
        'latitude' => true,
        'longitude' => true,
        'public_page_enabled' => true,
        'cancelled_at' => true,
        'cancellation_reason' => true,
        'created_by' => true,
        'created' => true,
        'modified' => true,
        'branch' => true,
        'gathering_type' => true,
        'creator' => true,
        // Guard association; manage via controller actions
        'gathering_activities' => false,
        'gathering_attendances' => false,
        'gathering_scheduled_activities' => false,
        'gathering_staff' => false,
        'gathering_waivers' => false,
    ];

    /**
     * Virtual field for date range display
     *
     * Shows dates in the gathering's timezone for accurate representation
     * of when the event occurs at its location.
     *
     * @return string
     */
    protected function _getDateRange(): string
    {
        // Convert dates to gathering's timezone before display
        $startInTz = \App\KMP\TimezoneHelper::toUserTimezone($this->start_date, null, null, $this);
        $endInTz = \App\KMP\TimezoneHelper::toUserTimezone($this->end_date, null, null, $this);

        // Defensive check: return empty string if timezone conversion failed
        if ($startInTz === null || $endInTz === null) {
            return '';
        }

        if ($startInTz->format('Y-m-d') === $endInTz->format('Y-m-d')) {
            return $startInTz->format('Y-m-d');
        }

        return $startInTz->format('Y-m-d') . ' to ' . $endInTz->format('Y-m-d');
    }

    /**
     * Virtual field to check if gathering is multi-day
     *
     * Compares dates in the gathering's timezone (not UTC) to accurately
     * determine if the event spans multiple calendar days at its location.
     *
     * @return bool
     */
    protected function _getIsMultiDay(): bool
    {
        // Convert dates to gathering's timezone before comparing
        $startInTz = \App\KMP\TimezoneHelper::toUserTimezone($this->start_date, null, null, $this);
        $endInTz = \App\KMP\TimezoneHelper::toUserTimezone($this->end_date, null, null, $this);

        // Defensive check: if timezone conversion failed, assume single-day
        if ($startInTz === null || $endInTz === null) {
            return false;
        }

        // Compare calendar dates (not datetime equality) in the event's timezone
        return $startInTz->format('Y-m-d') !== $endInTz->format('Y-m-d');
    }

    /**
     * Virtual field to check if gathering is cancelled
     *
     * @return bool
     */
    protected function _getIsCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}