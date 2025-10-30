<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * GatheringActivityWaiver Entity
 *
 * Join table linking gathering activities to required waiver types.
 *
 * @property int $gathering_activity_id
 * @property int $waiver_type_id
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 * @property \Waivers\Model\Entity\WaiverType $waiver_type
 */
class GatheringActivityWaiver extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_activity_id' => true,
        'waiver_type_id' => true,
        'created' => true,
        'gathering_activity' => true,
        'waiver_type' => true,
    ];
}