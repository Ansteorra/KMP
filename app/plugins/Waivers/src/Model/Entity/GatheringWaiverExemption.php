<?php

declare(strict_types=1);

namespace Waivers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * GatheringWaiverExemption Entity
 *
 * Tracks attestations that a waiver was not needed for a specific activity/waiver type combination.
 * This allows authorized users to formally document why a required waiver was not collected.
 *
 * @property int $id
 * @property int $gathering_activity_id
 * @property int $waiver_type_id
 * @property string $reason
 * @property int $member_id
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 * @property \Waivers\Model\Entity\WaiverType $waiver_type
 * @property \App\Model\Entity\Member $member
 */
class GatheringWaiverExemption extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_activity_id' => true,
        'waiver_type_id' => true,
        'reason' => true,
        'member_id' => true,
        'notes' => true,
        'created' => true,
        'gathering_activity' => true,
        'waiver_type' => true,
        'member' => true,
    ];
}
