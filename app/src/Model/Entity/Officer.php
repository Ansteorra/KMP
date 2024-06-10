<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Officer Entity
 *
 * @property int $id
 * @property int $member_id
 * @property int $branch_id
 * @property int $office_id
 * @property int|null $granted_member_role_id
 * @property \Cake\I18n\Date|null $expires_on
 * @property \Cake\I18n\Date|null $start_on
 * @property string $status
 * @property string|null $revoked_reason
 * @property int|null $revoker_id
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Branch $branch
 * @property \App\Model\Entity\Office $office
 */
class Officer extends ActiveWindowBaseEntity
{
    public $typeIdField = ['office_id', 'branch_id'];
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
        'member_id' => true,
        'branch_id' => true,
        'office_id' => true,
        'granted_member_role_id' => true,
        'expires_on' => true,
        'start_on' => true,
        'status' => true,
        'revoked_reason' => true,
        'revoker_id' => true,
        'member' => true,
        'branch' => true,
        'office' => true,
    ];
}
