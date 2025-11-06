<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Authorization Entity
 * 
 * Represents a member's authorization to participate in a specific activity within the KMP system.
 * Authorizations implement temporal validation through ActiveWindowBaseEntity, providing time-bounded
 * access control for activity participation. Each authorization links a member to an activity with
 * defined start and expiration dates, tracking approval status and workflow state.
 * 
 * This entity extends ActiveWindowBaseEntity to inherit active window functionality, enabling
 * automatic lifecycle management, temporal validation, and integration with the ActiveWindowManager
 * service. Authorizations serve as the core mechanism for controlling member access to activities
 * requiring special permissions, training, or approval.
 * 
 * ## Database Schema
 * - `id` (int): Primary key identifier
 * - `member_id` (int): Foreign key to Member entity - who is authorized
 * - `activity_id` (int): Foreign key to Activity entity - what they're authorized for
 * - `expires_on` (Date): Required expiration date for the authorization
 * - `start_on` (Date, nullable): Optional start date (defaults to creation date)
 * - Inherits ActiveWindow fields: status, notes, active window management
 * - Inherits audit fields from BaseEntity: created, modified, created_by, modified_by
 * 
 * ## Entity Relationships
 * - **belongsTo Member**: The member receiving the authorization
 * - **belongsTo Activity**: The activity the member is authorized for
 * - **hasMany AuthorizationApproval**: Approval workflow tracking
 * 
 * ## Authorization Status Constants
 * The authorization lifecycle is tracked through five distinct statuses:
 * - `APPROVED_STATUS` ("Approved"): Authorization is active and valid
 * - `PENDING_STATUS` ("Pending"): Authorization awaiting approval
 * - `DENIED_STATUS` ("Denied"): Authorization request was rejected
 * - `REVOKED_STATUS` ("Revoked"): Previously approved authorization was revoked
 * - `EXPIRED_STATUS` ("Expired"): Authorization has passed its expiration date
 * 
 * ## ActiveWindow Integration
 * Authorizations utilize the ActiveWindow system for lifecycle management:
 * - Automatic status transitions based on start_on and expires_on dates
 * - Integration with ActiveWindowManager for temporal operations
 * - Support for overlapping authorization windows with automatic replacement
 * - Temporal validation ensuring only current authorizations are effective
 * 
 * ## Security Architecture
 * Authorization entities implement comprehensive security measures:
 * - Mass assignment protection limiting accessible fields to prevent tampering
 * - Temporal validation ensuring authorizations are only valid within defined windows
 * - Integration with RBAC system for permission-based authorization checking
 * - Audit trail inheritance providing complete change tracking
 * 
 * ## Business Logic
 * Authorization workflow encompasses complete lifecycle management:
 * 
 * ### Request → Approval → Active → Expiration
 * 1. **Request**: Authorization created with PENDING_STATUS
 * 2. **Approval Process**: AuthorizationApproval entities track approver responses
 * 3. **Activation**: Status changes to APPROVED_STATUS when approved
 * 4. **Expiration**: Automatic transition to EXPIRED_STATUS after expires_on date
 * 5. **Revocation**: Manual status change to REVOKED_STATUS if needed
 * 
 * ## Usage Examples
 * 
 * ### Creating Authorization Requests
 * ```php
 * // Request authorization for heavy weapons combat
 * $authorization = $authorizationsTable->newEntity([
 *     'member_id' => $member->id,
 *     'activity_id' => $heavyWeaponsActivity->id,
 *     'start_on' => FrozenDate::now(),
 *     'expires_on' => FrozenDate::now()->addYears(2),
 *     'status' => Authorization::PENDING_STATUS
 * ]);
 * $authorizationsTable->save($authorization);
 * ```
 * 
 * ### Checking Authorization Status
 * ```php
 * // Check if member has current authorization for activity
 * $currentAuth = $authorizationsTable->find()
 *     ->where([
 *         'member_id' => $member->id,
 *         'activity_id' => $activity->id,
 *         'status' => Authorization::APPROVED_STATUS
 *     ])
 *     ->where(['start_on <=' => FrozenDate::now()])
 *     ->where(['expires_on >=' => FrozenDate::now()])
 *     ->first();
 * 
 * if ($currentAuth) {
 *     // Member is currently authorized
 *     $daysRemaining = $currentAuth->expires_on->diffInDays(FrozenDate::now());
 * }
 * ```
 * 
 * ### Authorization Approval Workflow
 * ```php
 * // Process authorization approval
 * $authorizationManager = $this->getService('AuthorizationManagerInterface');
 * $result = $authorizationManager->approve($authorization, $approver, [
 *     'notes' => 'Member has completed required training',
 *     'approved' => true
 * ]);
 * 
 * if ($result->isSuccess()) {
 *     // Authorization approved and activated
 *     $authorization = $result->getData();
 * }
 * ```
 * 
 * ### Temporal Authorization Queries
 * ```php
 * // Find all authorizations expiring in next 30 days
 * $expiringAuths = $authorizationsTable->find()
 *     ->where(['status' => Authorization::APPROVED_STATUS])
 *     ->where(['expires_on >=' => FrozenDate::now()])
 *     ->where(['expires_on <=' => FrozenDate::now()->addDays(30)])
 *     ->contain(['Member', 'Activity'])
 *     ->all();
 * 
 * // Get member's authorization history for activity
 * $authHistory = $authorizationsTable->find()
 *     ->where([
 *         'member_id' => $member->id,
 *         'activity_id' => $activity->id
 *     ])
 *     ->orderBy(['created' => 'DESC'])
 *     ->contain(['AuthorizationApprovals.Member'])
 *     ->all();
 * ```
 * 
 * ### ActiveWindow Integration
 * ```php
 * // Use ActiveWindowManager for authorization lifecycle
 * $activeWindowManager = $this->getService('ActiveWindowManagerInterface');
 * 
 * // Start authorization window
 * $result = $activeWindowManager->start($authorization, [
 *     'start_on' => FrozenDate::now(),
 *     'expires_on' => FrozenDate::now()->addYears(1)
 * ]);
 * 
 * // Replace expiring authorization with new one
 * $newAuth = $authorizationsTable->newEntity([...]);
 * $result = $activeWindowManager->replace($currentAuth, $newAuth);
 * ```
 * 
 * ## Integration Points
 * - **ActiveWindowManager**: Temporal lifecycle management and automatic transitions
 * - **AuthorizationManager**: Business logic service for approval workflows
 * - **PermissionsLoader**: RBAC integration for permission validation
 * - **Activities System**: Core authorization requirements and approver discovery
 * - **Member Management**: Authorization tracking and member activity participation
 * 
 * ## Performance Considerations
 * - Indexed queries on member_id + activity_id + status for efficient lookup
 * - Temporal queries optimized with date range indexes
 * - ActiveWindow behavior provides automatic status management
 * - Approval workflow tracked through separate AuthorizationApproval entities
 * 
 * @property int $id Primary key identifier
 * @property int $member_id Foreign key to Member entity
 * @property int $activity_id Foreign key to Activity entity  
 * @property \Cake\I18n\Date $expires_on Required expiration date
 * @property \Cake\I18n\Date|null $start_on Optional start date
 * 
 * @property \App\Model\Entity\Member $member The member receiving authorization
 * @property \Activities\Model\Entity\Activity $activity The activity being authorized
 * @property \Activities\Model\Entity\AuthorizationApproval[] $authorization_approvals Approval workflow tracking
 * 
 * @see \Activities\Model\Table\AuthorizationsTable Authorizations table class
 * @see \Activities\Services\AuthorizationManagerInterface Authorization business logic service
 * @see \App\Model\Entity\ActiveWindowBaseEntity Base entity with temporal functionality
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface ActiveWindow lifecycle management
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
