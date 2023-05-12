<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * AuthorizationType Entity
 *
 * @property int $id
 * @property string $name
 * @property int $length
 * @property int $martial_groups_id
 *
 * @property \App\Model\Entity\MartialGroup $martial_group
 * @property \App\Model\Entity\ParticipantAuthorizationType[] $participant_authorization_types
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations
 * @property \App\Model\Entity\Role[] $roles
 */
class AuthorizationType extends Entity
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
    protected $_accessible = [
        '*' => true,
        'id' => false
    ];
}
