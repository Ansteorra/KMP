<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PendingAuthorization Entity
 *
 * @property int $id
 * @property int $participant_id
 * @property int $participant_marshal_id
 * @property int $authorization_type_id
 * @property string $authorization_token
 * @property \Cake\I18n\Time $requested_on
 *
 * @property \App\Model\Entity\Participant $participant
 * @property \App\Model\Entity\AuthorizationType $authorization_type
 */
class PendingAuthorization extends Entity
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
