<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;

/**
 * GatheringWaiverActivity Entity
 *
 * Join table linking gathering waivers to the activities they cover.
 *
 * @property int $gathering_waiver_id
 * @property int $gathering_activity_id
 * @property \Cake\I18n\DateTime $created
 *
 * @property \Waivers\Model\Entity\GatheringWaiver $gathering_waiver
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 */
class GatheringWaiverActivity extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_waiver_id' => true,
        'gathering_activity_id' => true,
        'created' => true,
        'gathering_waiver' => true,
        'gathering_activity' => true,
    ];
}
