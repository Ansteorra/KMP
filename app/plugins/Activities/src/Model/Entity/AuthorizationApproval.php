<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * AuthorizationApproval Entity
 * 
 * Represents an individual approver's response within the authorization approval workflow.
 * Each AuthorizationApproval tracks a single approver's decision (approve/deny) for a specific
 * authorization request, including timing, notes, and secure token validation. This entity
 * enables multi-level approval workflows where multiple approvers may be required.
 * 
 * This entity extends BaseEntity to inherit standard audit trail functionality and branch
 * authorization integration. Authorization approvals form the detailed approval history,
 * providing complete traceability and accountability for authorization decisions.
 * 
 * ## Database Schema
 * - `id` (int): Primary key identifier
 * - `authorization_id` (int): Foreign key to Authorization entity being approved
 * - `approver_id` (int): Foreign key to Member entity acting as approver
 * - `authorization_token` (string): Secure token for email-based approval validation
 * - `requested_on` (Date): When approval was requested from this approver
 * - `responded_on` (Date, nullable): When approver provided their response
 * - `approved` (bool): Approver's decision (true = approved, false = denied)
 * - `approver_notes` (string, nullable): Optional notes from approver explaining decision
 * - Inherits audit fields from BaseEntity: created, modified, created_by, modified_by
 * 
 * ## Entity Relationships
 * - **belongsTo Authorization**: The authorization request being evaluated
 * - **belongsTo Member** (as approver): The member acting as approver
 * 
 * ## Approval Workflow Architecture
 * AuthorizationApproval entities implement a secure, traceable approval process:
 * 
 * ### Token-Based Security
 * - Each approval includes a unique `authorization_token` for secure email validation
 * - Tokens prevent unauthorized approval manipulation and ensure approver authenticity
 * - Email-based approval links include tokens for direct approval/denial actions
 * 
 * ### Multi-Level Approval Support
 * - Multiple AuthorizationApproval entities can exist for single Authorization
 * - Each approver receives individual approval request with unique tracking
 * - Approval logic can require unanimous, majority, or any-approver validation
 * 
 * ### Approval Timeline Tracking
 * - `requested_on`: When approval was requested (set on creation)
 * - `responded_on`: When approver responded (set when approval/denial submitted)
 * - Response time tracking enables approval workflow analytics and reminders
 * 
 * ## Security Architecture
 * Authorization approval implements comprehensive security measures:
 * - Secure token validation preventing unauthorized approval manipulation
 * - Audit trail inheritance providing complete approval decision tracking
 * - Mass assignment protection limiting accessible fields to prevent tampering
 * - Integration with RBAC system ensuring only authorized approvers can respond
 * 
 * ## Business Logic
 * Authorization approval workflow encompasses complete decision tracking:
 * 
 * ### Request → Review → Decision → Completion
 * 1. **Request**: AuthorizationApproval created with `requested_on` timestamp
 * 2. **Notification**: Approver receives email with secure approval token
 * 3. **Review**: Approver evaluates authorization request and requirements
 * 4. **Decision**: Approver submits approval/denial with optional notes
 * 5. **Completion**: `responded_on` timestamp recorded with decision
 * 
 * ## Usage Examples
 * 
 * ### Creating Approval Requests
 * ```php
 * // Create approval request for heavy weapons instructor
 * $approval = $authorizationApprovalsTable->newEntity([
 *     'authorization_id' => $authorization->id,
 *     'approver_id' => $instructor->id,
 *     'authorization_token' => Security::randomString(32),
 *     'requested_on' => FrozenDate::now(),
 *     'approved' => null, // No decision yet
 *     'approver_notes' => null
 * ]);
 * $authorizationApprovalsTable->save($approval);
 * ```
 * 
 * ### Processing Approval Responses
 * ```php
 * // Process approver decision via secure token
 * $approval = $authorizationApprovalsTable->find()
 *     ->where(['authorization_token' => $token])
 *     ->where(['responded_on IS' => null]) // Not yet responded
 *     ->first();
 * 
 * if ($approval) {
 *     $approval = $authorizationApprovalsTable->patchEntity($approval, [
 *         'responded_on' => FrozenDate::now(),
 *         'approved' => $decision, // true or false
 *         'approver_notes' => $notes
 *     ]);
 *     $authorizationApprovalsTable->save($approval);
 * }
 * ```
 * 
 * ### Multi-Level Approval Logic
 * ```php
 * // Check if authorization has required approvals
 * $approvals = $authorizationApprovalsTable->find()
 *     ->where(['authorization_id' => $authorization->id])
 *     ->where(['responded_on IS NOT' => null])
 *     ->all();
 * 
 * $totalApprovals = $approvals->count();
 * $approvedCount = $approvals->where(['approved' => true])->count();
 * $deniedCount = $approvals->where(['approved' => false])->count();
 * 
 * // Check approval requirements (example: unanimous approval required)
 * $requiredApprovers = $authorization->activity->getApproversQuery()->count();
 * if ($approvedCount === $requiredApprovers) {
 *     // All required approvers have approved
 *     $authorization->status = Authorization::APPROVED_STATUS;
 * } elseif ($deniedCount > 0) {
 *     // Any denial rejects the authorization
 *     $authorization->status = Authorization::DENIED_STATUS;
 * }
 * ```
 * 
 * ### Approval Analytics and Reporting
 * ```php
 * // Generate approval statistics for activity
 * $approvalStats = $authorizationApprovalsTable->find()
 *     ->select([
 *         'approver_id',
 *         'approved_count' => $authorizationApprovalsTable->find()->func()->count('CASE WHEN approved = 1 THEN 1 END'),
 *         'denied_count' => $authorizationApprovalsTable->find()->func()->count('CASE WHEN approved = 0 THEN 1 END'),
 *         'avg_response_time' => $authorizationApprovalsTable->find()->func()->avg('DATEDIFF(responded_on, requested_on)')
 *     ])
 *     ->contain(['Member'])
 *     ->where(['Authorization.activity_id' => $activity->id])
 *     ->groupBy(['approver_id'])
 *     ->all();
 * ```
 * 
 * ### Pending Approval Management
 * ```php
 * // Find pending approvals for member (as approver)
 * $pendingApprovals = $authorizationApprovalsTable->find()
 *     ->where([
 *         'approver_id' => $member->id,
 *         'responded_on IS' => null
 *     ])
 *     ->contain(['Authorization.Member', 'Authorization.Activity'])
 *     ->orderBy(['requested_on' => 'ASC'])
 *     ->all();
 * 
 * // Find overdue approvals (requested > 7 days ago)
 * $overdueApprovals = $authorizationApprovalsTable->find()
 *     ->where(['responded_on IS' => null])
 *     ->where(['requested_on <' => FrozenDate::now()->subDays(7)])
 *     ->contain(['Member', 'Authorization.Member', 'Authorization.Activity'])
 *     ->all();
 * ```
 * 
 * ### Email-Based Approval Integration
 * ```php
 * // Generate secure approval URLs for email notifications
 * $approveUrl = Router::url([
 *     'plugin' => 'Activities',
 *     'controller' => 'AuthorizationApprovals', 
 *     'action' => 'respond',
 *     'token' => $approval->authorization_token,
 *     'decision' => 'approve'
 * ], true);
 * 
 * $denyUrl = Router::url([
 *     'plugin' => 'Activities',
 *     'controller' => 'AuthorizationApprovals',
 *     'action' => 'respond', 
 *     'token' => $approval->authorization_token,
 *     'decision' => 'deny'
 * ], true);
 * ```
 * 
 * ## Integration Points
 * - **AuthorizationManager**: Service coordinating approval workflow and status updates
 * - **Email System**: Notification delivery for approval requests and responses
 * - **Activity Management**: Approver discovery and requirement validation
 * - **Member Management**: Approver authentication and authorization validation
 * - **Navigation System**: Pending approval badges and workflow navigation
 * 
 * ## Performance Considerations
 * - Indexed queries on authorization_id + responded_on for approval status checks
 * - Token-based lookups require unique index on authorization_token
 * - Approval history queries optimized with authorization_id indexing
 * - Response time calculations benefit from date field indexing
 * 
 * @property int $id Primary key identifier
 * @property int $authorization_id Foreign key to Authorization entity
 * @property int $approver_id Foreign key to Member entity (approver)
 * @property string $authorization_token Secure token for email validation
 * @property \Cake\I18n\Date $requested_on When approval was requested
 * @property \Cake\I18n\Date|null $responded_on When approver responded
 * @property bool $approved Approver's decision (true = approved, false = denied)
 * @property string|null $approver_notes Optional notes from approver
 * 
 * @property \Activities\Model\Entity\Authorization $authorization The authorization being evaluated
 * @property \App\Model\Entity\Member $member The approver member
 * 
 * @see \Activities\Model\Table\AuthorizationApprovalsTable Authorization approvals table class
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow management service
 * @see \Activities\Model\Entity\Authorization Authorization entity being approved
 * @see \App\Model\Entity\BaseEntity Base entity with audit trail functionality
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
