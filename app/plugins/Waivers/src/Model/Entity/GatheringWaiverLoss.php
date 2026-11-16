<?php
declare(strict_types=1);

namespace Waivers\Model\Entity;

use App\Model\Entity\BaseEntity;

/** Loss notes and recording details remain intact when the loss is reversed. */
class GatheringWaiverLoss extends BaseEntity
{
    protected array $_accessible = [
        'gathering_id' => true,
        'waiver_type_id' => true,
        'notes' => true,
    ];
}
