<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * GatheringWaiverClosure Entity
 *
 * Represents a closed waiver collection window for a gathering.
 *
 * @property int $id
 * @property int $gathering_id
 * @property \Cake\I18n\DateTime $closed_at
 * @property int $closed_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\Member $closed_by_member
 */
class GatheringWaiverClosure extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_id' => true,
        'closed_at' => true,
        'closed_by' => true,
        'created' => true,
        'modified' => true,
        'gathering' => true,
        'closed_by_member' => true,
    ];
}
