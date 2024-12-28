<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Warrant Entity
 *
 * @property int $id
 * @property int $member_id
 * @property int $warrant_roster_id
 * @property string|null $entity_type
 * @property int $entity_id
 * @property int|null $member_role_id
 * @property \Cake\I18n\DateTime|null $expires_on
 * @property \Cake\I18n\DateTime|null $start_on
 * @property \Cake\I18n\DateTime|null $approved_date
 * @property string $status
 * @property string|null $revoked_reason
 * @property int|null $revoker_id
 * @property int|null $created_by
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\WarrantRoster $warrant_roster_approval_set
 * @property \App\Model\Entity\MemberRole $member_role
 */
class Warrant extends ActiveWindowBaseEntity
{

    const PENDING_STATUS = "Pending";

    public array $typeIdField = ['member_role_id'];
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
        'warrant_roster_id' => true,
        'entity_type' => true,
        'entity_id' => true,
        'member_role_id' => true,
        'expires_on' => true,
        'start_on' => true,
        'approved_date' => true,
        'status' => true,
        'revoked_reason' => true,
        'revoker_id' => true,
        'created_by' => true,
        'created' => true,
        'member' => true,
        'warrant_roster_approval_set' => true,
        'member_role' => true,
    ];
}