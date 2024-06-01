<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * MemberRole Entity
 *
 * @property int $id
 * @property int $Member_id
 * @property int $role_id
 * @property \Cake\I18n\Date|null $expires_on
 * @property \Cake\I18n\Date $start_on
 * @property int $approver_id
 *
 * @property \App\Model\Entity\Member $Member
 * @property \App\Model\Entity\Role $role
 * @property \App\Model\Entity\Member $approved_by
 */
class MemberRole extends Entity
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
        "Member_id" => true,
        "role_id" => true,
        "expires_on" => true,
        "start_on" => true,
        "approver_id" => true,
        "Member" => true,
        "role" => true,
        "approved_by" => true,
    ];
}
