<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Warrant Entity - Temporal Validation Layer for KMP RBAC Security System
 *
 * The Warrant entity serves as the critical temporal validation layer for the KMP Role-Based
 * Access Control (RBAC) system. Warrants provide time-bounded authorization that determines
 * when role-based permissions are actually active and enforceable in the system.
 *
 * ## Core Warrant Architecture
 *
 * ### RBAC Integration Layer
 * Warrants integrate directly with the RBAC permission validation chain:
 * ```
 * Members → MemberRoles → Roles → Permissions
 *              ↓
 *          Warrants (Temporal Validation)
 *              ↓
 *         Active Permissions
 * ```
 *
 * When `KMP.RequireActiveWarrantForSecurity` is enabled, the PermissionsLoader validates
 * permissions only when:
 * - Member has the role assignment (MemberRole)
 * - AND there's an active warrant for that role assignment
 * - AND the warrant is within its valid time period
 * - AND the warrant status is 'Current'
 *
 * ### Warrant Lifecycle States
 * Warrants follow a comprehensive state machine with seven distinct states:
 * - **Pending**: Awaiting approval through warrant roster system
 * - **Current**: Active and providing temporal validation for permissions
 * - **Expired**: Past expiration date, no longer valid for permission checks
 * - **Deactivated**: Administratively terminated before expiration
 * - **Cancelled**: Cancelled during pending state before activation
 * - **Declined**: Rejected during approval process
 * - **Replaced**: Superseded by newer warrant for same entity/member
 *
 * ### Temporal Validation Features
 * - **Start Date Control**: Warrants can be scheduled for future activation
 * - **Expiration Management**: Automatic expiration based on warrant periods
 * - **Administrative Control**: Manual activation, deactivation, and cancellation
 * - **Approval Workflows**: Multi-level approval through warrant roster system
 * - **Audit Trail**: Complete tracking of warrant lifecycle and modifications
 *
 * ## Entity Relationships and Integration
 *
 * ### Member Role Integration
 * - **member_role_id**: Links warrant to specific MemberRole assignment
 * - **Permission Validation**: PermissionsLoader checks warrant validity for role-based permissions
 * - **Temporal Scope**: Warrant period must overlap with role assignment period
 * - **Automatic Expiration**: Warrants expire when underlying roles expire
 *
 * ### Entity-Specific Warrants
 * - **entity_type**: Type of entity being warranted (Officers, Activities, Direct Grant)
 * - **entity_id**: Specific instance of the entity type
 * - **Flexible Authorization**: Support for office-specific, activity-specific, or general warrants
 * - **Scope Control**: Different warrant types provide different permission scopes
 *
 * ### Warrant Roster Integration
 * - **warrant_roster_id**: Links to batch approval system
 * - **Approval Tracking**: Multi-level approval requirements configurable
 * - **Batch Operations**: Efficient management of multiple related warrants
 * - **Approval Audit**: Complete tracking of who approved and when
 *
 * ## Security Architecture
 *
 * ### RBAC Security Enforcement
 * The PermissionsLoader performs warrant validation through this query pattern:
 * ```php
 * $warrantSubquery = $warrantsTable->find()
 *     ->select(['Warrants.member_role_id'])
 *     ->where([
 *         'Warrants.start_on <' => $now,      // Warrant has started
 *         'Warrants.expires_on >' => $now,    // Warrant hasn't expired
 *         'Warrants.status' => Warrant::CURRENT_STATUS,  // Warrant is active
 *     ]);
 * 
 * // Only permissions with valid warrants are granted
 * $q->where(['MemberRoles.id IN' => $warrantSubquery]);
 * ```
 *
 * ### Security Controls
 * - **Temporal Boundaries**: Strict enforcement of start and expiration dates
 * - **Status Validation**: Only 'Current' status warrants provide authorization
 * - **Revocation Tracking**: Complete audit trail for terminated warrants
 * - **Approval Requirements**: Configurable multi-level approval workflows
 *
 * ### Administrative Safeguards
 * - **System Protection**: Critical system warrants protected from accidental removal
 * - **Approval Tracking**: Complete record of approval chain and timing
 * - **Emergency Controls**: Administrative override capabilities for security incidents
 * - **Audit Trail**: Comprehensive logging of all warrant state changes
 *
 * ## Performance Optimization
 *
 * ### Permission Validation Optimization
 * - **Subquery Optimization**: Efficient SQL generation for warrant validation
 * - **Index Strategy**: Optimized database indexes for temporal queries
 * - **Cache Integration**: Security cache invalidation on warrant changes
 * - **Batch Processing**: Efficient handling of warrant roster operations
 *
 * ### Temporal Query Patterns
 * ```php
 * // Current active warrants
 * $warrants = $warrantsTable->find()
 *     ->where([
 *         'start_on <=' => $now,
 *         'expires_on >' => $now,
 *         'status' => Warrant::CURRENT_STATUS
 *     ]);
 * 
 * // Upcoming warrants
 * $upcoming = $warrantsTable->find()
 *     ->where([
 *         'start_on >' => $now,
 *         'status' => Warrant::CURRENT_STATUS
 *     ]);
 * ```
 *
 * ## Integration Examples
 *
 * ### Permission-Required Warrant Creation
 * ```php
 * // When assigning role with warrant-required permissions
 * $permissions = $this->Roles->Permissions->find()
 *     ->where(['requires_warrant' => true])
 *     ->count();
 * 
 * if ($permissions > 0) {
 *     $warrantRequest = new WarrantRequest(
 *         'Role Assignment: ' . $role->name,
 *         'Direct Grant',
 *         $role->id,
 *         $approverId,
 *         $memberId,
 *         $startDate,
 *         $endDate,
 *         $memberRole->id
 *     );
 *     
 *     $result = $warrantManager->request($description, '', [$warrantRequest]);
 * }
 * ```
 *
 * ### Warrant-Secured Permission Checking
 * ```php
 * // PermissionsLoader validates warrants for RBAC permissions
 * $permissions = $permissionsLoader->getPermissions($memberId);
 * // Only returns permissions where:
 * // 1. Member has role assignment
 * // 2. Role has permission
 * // 3. Valid warrant exists for role assignment (if required)
 * ```
 *
 * ### Administrative Warrant Management
 * ```php
 * // Deactivate warrant for security incident
 * $result = $warrantManager->cancel(
 *     $warrantId,
 *     'Security incident - immediate revocation',
 *     $adminId,
 *     DateTime::now()
 * );
 * 
 * // Approve warrant roster
 * $result = $warrantManager->approve($rosterId, $approverId);
 * ```
 *
 * ## Usage Examples
 *
 * ### Basic Warrant Queries
 * ```php
 * // Find active warrants for member
 * $activeWarrants = $warrantsTable->find()
 *     ->where([
 *         'member_id' => $memberId,
 *         'start_on <=' => DateTime::now(),
 *         'expires_on >' => DateTime::now(),
 *         'status' => Warrant::CURRENT_STATUS
 *     ]);
 * 
 * // Check specific warrant validity
 * $isValid = ($warrant->status === Warrant::CURRENT_STATUS) &&
 *            ($warrant->start_on <= DateTime::now()) &&
 *            ($warrant->expires_on > DateTime::now());
 * ```
 *
 * ### Permission Integration
 * ```php
 * // Check if member has warrant-secured permission
 * $hasPermission = $member->checkCan('manage.events', 'Activities');
 * // This internally validates:
 * // 1. Member has role with 'manage.events' permission
 * // 2. Valid warrant exists for the role assignment
 * // 3. All other permission requirements met (membership, age, etc.)
 * ```
 *
 * @see \App\Model\Entity\ActiveWindowBaseEntity For temporal entity base functionality
 * @see \App\Model\Table\WarrantsTable For warrant data management
 * @see \App\Model\Entity\WarrantRoster For batch warrant management
 * @see \App\Model\Entity\MemberRole For role assignment integration
 * @see \App\Services\WarrantManager\WarrantManagerInterface For warrant business logic
 * @see \App\KMP\PermissionsLoader For RBAC security validation engine
 *
 * @property int $id Unique warrant identifier
 * @property int $member_id Member receiving the warrant
 * @property int $warrant_roster_id Batch approval system reference
 * @property string|null $entity_type Type of entity being warranted (Officers, Activities, Direct Grant)
 * @property int $entity_id Specific entity instance identifier
 * @property int|null $member_role_id Associated member role assignment for permission validation
 * @property \Cake\I18n\DateTime|null $expires_on Warrant expiration date for temporal validation
 * @property \Cake\I18n\DateTime|null $start_on Warrant start date for temporal validation
 * @property \Cake\I18n\DateTime|null $approved_date When warrant was approved and activated
 * @property string $status Current warrant state (Pending, Current, Expired, etc.)
 * @property string|null $revoked_reason Reason for manual warrant termination
 * @property int|null $revoker_id Member who terminated the warrant
 * @property int|null $created_by Member who created the warrant request
 * @property \Cake\I18n\DateTime $created Warrant creation timestamp
 *
 * @property \App\Model\Entity\Member $member Member receiving the warrant
 * @property \App\Model\Entity\WarrantRoster $warrant_roster_approval_set Batch approval container
 * @property \App\Model\Entity\MemberRole $member_role Associated role assignment for RBAC integration
 */
class Warrant extends ActiveWindowBaseEntity
{
    // Additional warrant-specific statuses beyond base ActiveWindow statuses
    public const PENDING_STATUS = 'Pending';
    public const DECLINED_STATUS = 'Declined';

    /**
     * Type identification field for ActiveWindow behavior
     * 
     * Specifies which field identifies the "type" of this active window entity.
     * For warrants, this is the member_role_id which links to the specific
     * role assignment that this warrant validates.
     *
     * @var array<string>
     */
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
