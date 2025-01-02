<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WarrantRosterApproval Entity
 *
 * @property int $id
 * @property int $warrant_roster_id
 * @property int $approver_id
 * @property string $authorization_token
 * @property \Cake\I18n\DateTime $requested_on
 * @property \Cake\I18n\DateTime|null $responded_on
 * @property bool $approved
 * @property string|null $approver_notes
 *
 * @property \App\Model\Entity\WarrantRoster $warrant_roster_approval_set
 * @property \App\Model\Entity\Member $member
 */
class WarrantRosterApproval extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'warrant_roster_id' => true,
        'approver_id' => true,
        'authorization_token' => true,
        'requested_on' => true,
        'responded_on' => true,
        'approved' => true,
        'approver_notes' => true,
        'warrant_roster_approval_set' => true,
        'member' => true,
    ];
}
