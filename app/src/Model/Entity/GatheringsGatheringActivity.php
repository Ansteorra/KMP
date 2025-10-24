<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringsGatheringActivity Entity
 *
 * Join entity for the many-to-many relationship between Gatherings and GatheringActivities.
 *
 * @property int $id
 * @property int $gathering_id
 * @property int $gathering_activity_id
 * @property int $sort_order
 * @property string|null $custom_description
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 */
class GatheringsGatheringActivity extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'gathering_activity_id' => true,
        'sort_order' => true,
        'custom_description' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'gathering' => true,
        'gathering_activity' => true,
    ];
}
