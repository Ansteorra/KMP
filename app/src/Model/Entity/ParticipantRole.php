<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ParticipantRole Entity
 *
 * @property int $participant_id
 * @property int $role_id
 * @property \Cake\I18n\Date|null $ended_on
 * @property \Cake\I18n\Date $start_on
 * @property int $authorized_by_id
 *
 * @property \App\Model\Entity\Participant $participant
 * @property \App\Model\Entity\Role $role
 */
class ParticipantRole extends Entity
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
        'ended_on' => true,
        'start_on' => true,
        'authorized_by_id' => true,
        'participant' => true,
        'role' => true,
    ];
}
