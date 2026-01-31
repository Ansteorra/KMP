<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * GatheringWaiverClosure Entity
 *
 * Represents closure status for waiver collection on a gathering.
 * Supports two states: "ready to close" (event staff signals completion)
 * and "closed" (waiver secretary confirms and closes).
 *
 * @property int $id
 * @property int $gathering_id
 * @property \Cake\I18n\DateTime|null $closed_at
 * @property int|null $closed_by
 * @property \Cake\I18n\DateTime|null $ready_to_close_at
 * @property int|null $ready_to_close_by
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \App\Model\Entity\Member|null $closed_by_member
 * @property \App\Model\Entity\Member|null $ready_to_close_by_member
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
        'ready_to_close_at' => true,
        'ready_to_close_by' => true,
        'created' => true,
        'modified' => true,
        'gathering' => true,
        'closed_by_member' => true,
        'ready_to_close_by_member' => true,
    ];

    /**
     * Check if this gathering is marked ready to close.
     *
     * @return bool
     */
    public function isReadyToClose(): bool
    {
        return $this->ready_to_close_at !== null;
    }

    /**
     * Check if this gathering is fully closed.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
