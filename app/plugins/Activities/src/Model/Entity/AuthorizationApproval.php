<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * AuthorizationApproval Entity
 *
 * Tracks an individual approver's response within the authorization approval workflow.
 * Manages secure token validation, approval decisions, and complete audit trail.
 *
 * **Key Responsibilities:**
 * - Store approver decision (approve/deny) for specific authorization requests
 * - Maintain secure tokens for email-based approval workflows
 * - Track timing of approval requests and responses
 * - Provide complete approval history for audit and compliance
 *
 * **Core Fields:**
 * - `authorization_id`: Links to Authorization entity
 * - `approver_id`: Member making the approval decision
 * - `authorization_token`: Secure token for email validation
 * - `requested_on`: When approval request was sent
 * - `responded_on`: When approver responded (NULL if pending)
 * - `approved`: Final decision (true/false)
 * - `approver_notes`: Optional decision justification
 *
 * **Audit Fields (inherited from BaseEntity):**
 * - `created`, `modified`: Automatic timestamps
 * - `created_by`, `modified_by`: User accountability
 *
 * **Security:**
 * - Unique authorization_token prevents unauthorized approval manipulation
 * - Audit trail provides complete decision accountability
 * - Mass assignment protection on core fields
 * - Integration with RBAC for approver authority validation
 *
 * @property int $id Primary key
 * @property int $authorization_id Foreign key to Authorization
 * @property int $approver_id Foreign key to Member (approver)
 * @property string $authorization_token Secure email validation token
 * @property \Cake\I18n\Date $requested_on Request timestamp
 * @property \Cake\I18n\Date|null $responded_on Response timestamp
 * @property bool $approved Approval decision (true = approved, false = denied)
 * @property string|null $approver_notes Optional decision notes
 *
 * @property \Activities\Model\Entity\Authorization $authorization The authorization being evaluated
 * @property \App\Model\Entity\Member $member The approver member
 *
 * @see \Activities\Model\Table\AuthorizationApprovalsTable Authorization approvals table
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
 * @see \App\Model\Entity\BaseEntity Base entity with audit trail
 * @see 5.6.8-authorization-approval-entity-reference.md Comprehensive technical reference
 */
class AuthorizationApproval extends BaseEntity
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