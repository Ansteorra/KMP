<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Authorization Entity
 * 
 * Represents a member's authorization to participate in a specific activity. Extends ActiveWindowBaseEntity
 * for automatic temporal lifecycle management. Tracks approval workflow state, expiration dates, and role assignments.
 * 
 * **Status Constants:** APPROVED_STATUS, PENDING_STATUS, DENIED_STATUS, REVOKED_STATUS, EXPIRED_STATUS, RETRACTED_STATUS
 * 
 * **Database Fields:**
 * - member_id, activity_id, expires_on, start_on, status, approval_count
 * - granted_member_role_id, revoker_id, revoked_reason, is_renewal
 * 
 * **Relationships:**
 * - belongsTo Member, Activity, MemberRole (granted_member_role_id)
 * - belongsTo RevokedBy (revoker_id) - who revoked
 * - hasMany AuthorizationApprovals - workflow tracking
 * 
 * @property int $id
 * @property int $member_id
 * @property int $activity_id
 * @property int|null $granted_member_role_id
 * @property \Cake\I18n\DateTime|null $expires_on
 * @property \Cake\I18n\DateTime|null $start_on
 * @property \Cake\I18n\DateTime $created
 * @property int $approval_count
 * @property string $status
 * @property string $revoked_reason
 * @property int|null $revoker_id
 * @property bool $is_renewal
 * 
 * @property \App\Model\Entity\Member $member
 * @property \Activities\Model\Entity\Activity $activity
 * @property \App\Model\Entity\MemberRole $member_role
 * @property \App\Model\Entity\Member $revoked_by
 * @property \Activities\Model\Entity\AuthorizationApproval[] $authorization_approvals
 * 
 * @see \Activities\Model\Table\AuthorizationsTable Table class
 * @see 5.6.7-authorization-entity-reference.md Complete documentation
 */
class Authorization extends ActiveWindowBaseEntity
{
    const APPROVED_STATUS = "Approved";
    const PENDING_STATUS = "Pending";
    const DENIED_STATUS = "Denied";
    const REVOKED_STATUS = "Revoked";
    const EXPIRED_STATUS = "Expired";
    const RETRACTED_STATUS = "Retracted";

    public array $typeIdField = ['activity_id', 'member_id'];
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
