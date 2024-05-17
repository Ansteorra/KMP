<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * MemberAuthorizationType Entity
 *
 * @property int $id
 * @property int $Member_id
 * @property int $authorization_type_id
 * @property string $authorized_by
 * @property \Cake\I18n\Time $expires_on
 *
 * @property \App\Model\Entity\Member $Member
 * @property \App\Model\Entity\AuthorizationType $authorization_type
 */
class MemberAuthorizationType extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false
    ];
}