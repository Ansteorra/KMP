<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringActivity Entity
 *
 * Represents an activity at a gathering (e.g., Heavy Combat, Archery, A&S Display).
 * Activities can have associated waiver requirements.
 *
 * @property int $id
 * @property int $gathering_id
 * @property string $name
 * @property string|null $description
 * @property string|null $instructions
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \Waivers\Model\Entity\GatheringActivityWaiver[] $gathering_activity_waivers
 * @property \Waivers\Model\Entity\GatheringWaiverActivity[] $gathering_waiver_activities
 */
class GatheringActivity extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'name' => true,
        'description' => true,
        'created' => true,
        'modified' => true,
        'gathering' => true,
        'gathering_activity_waivers' => true,
        'gathering_waiver_activities' => true,
    ];
}
