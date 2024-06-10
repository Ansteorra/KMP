<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Authorization Entity
 *
 * @property int $id
 * @property int $member_id
 * @property int $activity_id
 * @property \Cake\I18n\Date $expires_on
 * @property \Cake\I18n\Date|null $start_on
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Activity $activity
 * @property \App\Model\Entity\AuthorizationApproval[] $authorization_approvals
 */
class Authorization extends ActiveWindowBaseEntity
{
    public $typeIdField = 'activity_id';
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
        "member_id" => true,
        "activity_id" => true,
        "expires_on" => true,
        "start_on" => true,
        "member" => true,
        "activity" => true,
        "authorization_approvals" => true,
    ];
}