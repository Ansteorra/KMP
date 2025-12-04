<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Warrant Entity - Temporal Validation for RBAC Security
 *
 * Time-bounded authorization determining when role permissions are active.
 * Integrates with PermissionsLoader for security validation.
 *
 * Lifecycle: Pending → Current → Expired/Deactivated/Cancelled/Declined/Replaced
 *
 * @see /docs/4.3-warrant-lifecycle.md For complete warrant documentation
 *
 * @property int $id Primary key
 * @property int $member_id Member receiving warrant
 * @property int $warrant_roster_id Batch approval reference
 * @property string|null $entity_type Officers, Activities, or Direct Grant
 * @property int $entity_id Entity instance ID
 * @property int|null $member_role_id MemberRole for permission validation
 * @property \Cake\I18n\DateTime|null $expires_on Expiration date
 * @property \Cake\I18n\DateTime|null $start_on Start date
 * @property \Cake\I18n\DateTime|null $approved_date Approval timestamp
 * @property string $status Pending, Current, Expired, Deactivated, etc.
 * @property string|null $revoked_reason Termination reason
 * @property int|null $revoker_id Who terminated the warrant
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\WarrantRoster $warrant_roster_approval_set
 * @property \App\Model\Entity\MemberRole $member_role
 */
class Warrant extends ActiveWindowBaseEntity
{
    // Additional warrant-specific statuses beyond base ActiveWindow statuses
    public const PENDING_STATUS = 'Pending';
    public const DECLINED_STATUS = 'Declined';

    /** @var array<string> Type ID field for ActiveWindow behavior */
    public array $typeIdField = ['member_role_id'];
    /** @var array<string, bool> Mass assignment fields */
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
