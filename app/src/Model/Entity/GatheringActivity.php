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
 */
class GatheringActivity extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'created' => true,
        'modified' => true,
    ];
}
