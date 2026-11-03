<?php
declare(strict_types=1);

namespace Waivers\Model\Entity;

use App\Model\Entity\BaseEntity;

/** An immutable report of required waivers lost before upload. */
class GatheringWaiverLoss extends BaseEntity
{
    protected array $_accessible = [
        'gathering_id' => true,
        'waiver_type_id' => true,
        'notes' => true,
    ];
}
