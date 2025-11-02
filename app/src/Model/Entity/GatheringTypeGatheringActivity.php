<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringTypeGatheringActivity Entity
 *
 * Join entity for the many-to-many relationship between GatheringTypes and GatheringActivities.
 * This defines template activities that should be automatically added to gatherings.
 *
 * @property int $id
 * @property int $gathering_type_id
 * @property int $gathering_activity_id
 * @property bool $not_removable
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\GatheringType $gathering_type
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 */
class GatheringTypeGatheringActivity extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_type_id' => true,
        'gathering_activity_id' => true,
        'not_removable' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'gathering_type' => true,
        'gathering_activity' => true,
    ];
}
