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
 * @property \Cake\I18n\Date $start_date
 * @property \Cake\I18n\Date $end_date
 * @property string|null $location
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $created_by
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Branch $branch
 * @property \App\Model\Entity\GatheringType $gathering_type
 * @property \App\Model\Entity\Member $creator
 * @property \App\Model\Entity\GatheringActivity[] $gathering_activities
 * @property \App\Model\Entity\GatheringAttendance[] $gathering_attendances
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
        'latitude' => true,
        'longitude' => true,
        'created_by' => true,
        'created' => true,
        'modified' => true,
        'branch' => true,
        'gathering_type' => true,
        'creator' => true,
        // Guard association; manage via controller actions
        'gathering_activities' => false,
        'gathering_attendances' => false,
        'gathering_waivers' => false,
    ];

    /**
     * Virtual field for date range display
     *
     * @return string
     */
    protected function _getDateRange(): string
    {
        if ($this->start_date->equals($this->end_date)) {
            return $this->start_date->format('Y-m-d');
        }

        return $this->start_date->format('Y-m-d') . ' to ' . $this->end_date->format('Y-m-d');
    }

    /**
     * Virtual field to check if gathering is multi-day
     *
     * @return bool
     */
    protected function _getIsMultiDay(): bool
    {
        return !$this->start_date->equals($this->end_date);
    }
}