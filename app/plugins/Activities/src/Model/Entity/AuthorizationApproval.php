<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuthorizationApproval Entity
 *
 * @property int $id
 * @property int $authorization_id
 * @property int $approver_id
 * @property string $authorization_token
 * @property \Cake\I18n\Date $requested_on
 * @property \Cake\I18n\Date|null $responded_on
 * @property bool $approved
 * @property string|null $approver_notes
 *
 * @property \App\Model\Entity\Authorization $authorization
 * @property \App\Model\Entity\Member $member
 */
class AuthorizationApproval extends Entity
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
        "authorization_id" => true,
        "approver_id" => true,
        "authorization_token" => true,
        "requested_on" => true,
        "responded_on" => true,
        "approved" => true,
        "approver_notes" => true,
        "authorization" => true,
        "member" => true,
    ];
}