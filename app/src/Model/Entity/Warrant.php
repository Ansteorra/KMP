<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Warrant Entity
 *
 * @property int $id
 * @property int $member_id
 * @property int $warrant_approval_set_id
 * @property string|null $warrant_for_model
 * @property int $warrant_for_id
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
 * @property \App\Model\Entity\WarrantApprovalSet $warrant_approval_set
 * @property \App\Model\Entity\MemberRole $member_role
 */
class Warrant extends ActiveWindowBaseEntity
{

    public array $typeIdField = ['member_role_id'];

    const STATUS_ACTIVE = "active"; //usable warrant
    const STATUS_DEACTIVATED = "deactivated"; //deactivated warrant
    const STATUS_PENDING = "pending"; //warrant awaiting approval
    const STATUS_EXPIRED = "expired"; //warrant awaiting approval
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
        'warrant_approval_set_id' => true,
        'warrant_for_model' => true,
        'warrant_for_id' => true,
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
        'warrant_approval_set' => true,
        'member_role' => true,
    ];
}