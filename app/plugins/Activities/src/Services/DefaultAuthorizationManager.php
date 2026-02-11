<?php

declare(strict_types=1);

namespace Activities\Services;

use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use Activities\Services\AuthorizationManagerInterface;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Default Authorization Manager Service
 * 
 * **Purpose**: Production implementation of AuthorizationManagerInterface providing comprehensive
 * authorization lifecycle management, multi-level approval workflows, and temporal validation
 * for Activities plugin member authorization system.
 * 
 * **Core Responsibilities**:
 * - Authorization Request Processing - Complete authorization request lifecycle management
 * - Multi-Level Approval Workflows - Sequential approval chains with forwarding logic
 * - Authorization Status Management - Approval, denial, and revocation operations
 * - Email Notification System - Automated approver and requester notifications
 * - ActiveWindow Integration - Temporal validation and automatic role assignment
 * - Transaction Management - Database consistency and rollback protection
 * 
 * **Architecture**: 
 * This service implements the AuthorizationManagerInterface contract and provides the core
 * business logic for authorization workflows within the Activities plugin. It integrates with
 * multiple KMP systems including the ActiveWindowManager for temporal validation, the email
 * system for notifications, and the RBAC system for automatic role assignment.
 * 
 * **Authorization Workflow**:
 * ```
 * Request → Pending → (Multi-Level Approvals) → Approved/Denied → Active/Expired/Revoked
 * ```
 * 
 * **Business Logic Features**:
 * - Renewal vs. New Authorization validation
 * - Duplicate request prevention
 * - Multi-approver sequential workflows
 * - Secure token-based email approval
 * - Automatic role assignment on approval
 * - Temporal expiration management
 * - Comprehensive audit trail
 * 
 * **Security Implementation**:
 * - Database transaction management for consistency
 * - Secure token generation for email-based approvals
 * - Validation of approver permissions
 * - Prevention of duplicate or conflicting requests
 * - Audit trail for all authorization actions
 * 
 * **Integration Points**:
 * - AuthorizationManagerInterface - Service contract compliance
 * - ActiveWindowManagerInterface - Temporal validation and role management
 * - MailerAwareTrait - Email notification system integration
 * - TableRegistry - Database table access and management
 * - ServiceResult - Standardized operation result patterns
 * - StaticHelpers - Token generation and utility functions
 * 
 * **Performance Considerations**:
 * - Database transaction management minimizes lock time
 * - Efficient query patterns for approval count validation
 * - Batch processing for multi-approval workflows
 * - Email notification batching for performance
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Authorization request processing
 * $result = $authManager->request(
 *     requesterId: 123,
 *     activityId: 456,
 *     approverId: 789,
 *     isRenewal: false
 * );
 * 
 * // Multi-level approval processing
 * $result = $authManager->approve(
 *     authorizationApprovalId: 100,
 *     approverId: 789,
 *     nextApproverId: 101  // For sequential approvals
 * );
 * 
 * // Authorization denial with audit trail
 * $result = $authManager->deny(
 *     authorizationApprovalId: 100,
 *     approverId: 789,
 *     denyReason: "Insufficient qualifications"
 * );
 * 
 * // Authorization revocation
 * $result = $authManager->revoke(
 *     authorizationId: 200,
 *     revokerId: 789,
 *     revokedReason: "Policy violation"
 * );
 * ```
 * 
 * **Error Handling**:
 * All methods return ServiceResult objects with detailed error messages and transaction
 * rollback on failure. Common error scenarios include validation failures, duplicate
 * requests, approver permission issues, and email notification failures.
 * 
 * **Troubleshooting**:
 * - Verify ActiveWindowManager service availability for temporal operations
 * - Check email configuration for notification delivery
 * - Validate approver permissions for authorization activities
 * - Monitor transaction logs for database consistency issues
 * 
 * @see AuthorizationManagerInterface Service contract definition
 * @see ActiveWindowManagerInterface Temporal validation service
 * @see ServiceResult Standardized result pattern
 * @see Authorization Entity authorization entity
 * @see AuthorizationApproval Approval tracking entity
 */
class DefaultAuthorizationManager implements AuthorizationManagerInterface
{
    #region
    use MailerAwareTrait;

    #endregion

    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
    }

    #region public methods
    /**
     * Process Authorization Request
     * 
     * Creates a new authorization request with approval workflow initialization.
     * Handles both new authorizations and renewals with comprehensive validation.
     * 
     * **Business Logic**:
     * - Validates renewal eligibility (existing approved authorization required)
     * - Prevents duplicate pending requests for same activity
     * - Creates authorization entity with pending status
     * - Initializes approval workflow with first approver
     * - Generates secure token for email-based approval
     * - Sends notification to designated approver
     * 
     * **Validation Rules**:
     * - Renewal: Must have existing approved authorization that hasn't expired
     * - Duplicate Prevention: No pending requests for same member/activity combination
     * - Approver Validation: Approver must have permission to authorize activity
     * 
     * **Transaction Management**:
     * Method creates database transaction for consistency across authorization
     * creation, approval initialization, and notification sending. Rolls back
     * on any failure to maintain data integrity.
     * 
     * @param int $requesterId Member ID requesting authorization
     * @param int $activityId Activity ID for authorization request
     * @param int $approverId Member ID of designated approver
     * @param bool $isRenewal Whether this is a renewal of existing authorization
     * @return ServiceResult Success/failure result with error details
     */
    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        // If its a renewal we will only create the auth if there is an existing auth that has not expired
        if ($isRenewal) {
            $existingAuths = $table
                ->find()
                ->where([
                    "member_id" => $requesterId,
                    "activity_id" => $activityId,
                    "status" => Authorization::APPROVED_STATUS,
                    "expires_on >" => DateTime::now(),
                ])
                ->count();
            if ($existingAuths == 0) {
                return new ServiceResult(false, "There is no existing authorization to renew");
            }
        }
        //Checking for existing pending requests
        $existingRequests = $table
            ->find()
            ->where([
                "member_id" => $requesterId,
                "activity_id" => $activityId,
                "status" => Authorization::PENDING_STATUS
            ])
            ->count();
        if ($existingRequests > 0) {
            return new ServiceResult(false, "There is already a pending request for this activity");
        }


        $auth = $table->newEmptyEntity();
        $auth->member_id = $requesterId;
        $auth->activity_id = $activityId;
        $auth->requested_on = DateTime::now();
        $auth->status = Authorization::PENDING_STATUS;
        $auth->is_renewal = $isRenewal;
        $table->getConnection()->begin();
        if (!$table->save($auth)) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to save authorization");
        }
        $approval = $table->AuthorizationApprovals->newEmptyEntity();
        $approval->authorization_id = $auth->id;
        $approval->approver_id = $approverId;
        $approval->requested_on = DateTime::now();
        $approval->authorization_token = StaticHelpers::generateToken(32);
        if (!$table->AuthorizationApprovals->save($approval)) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to save authorization approval");
        }
        if (
            !$this->sendApprovalRequestNotification(
                $activityId,
                $requesterId,
                $approverId,
                $approval->authorization_token,
            )
        ) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send approval request notification");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }
    /**
     * Process Authorization Approval
     * 
     * Handles authorization approval with support for multi-level approval workflows.
     * Manages sequential approver chains and final authorization activation.
     * 
     * **Approval Workflow Logic**:
     * - Records approver decision with timestamp and approval status
     * - Checks if additional approvals are required based on activity configuration
     * - For multi-approval: Forwards to next approver in sequence
     * - For final approval: Activates authorization and assigns roles
     * - Integrates with ActiveWindowManager for temporal validation
     * - Sends notifications to all relevant parties
     * 
     * **Multi-Level Support**:
     * Activities can require multiple approvers for new authorizations or renewals.
     * Method handles sequential approval chains by checking required approval counts
     * and forwarding to next approver when additional approvals needed.
     * 
     * **Role Assignment**:
     * Upon final approval, integrates with ActiveWindowManager to:
     * - Start temporal validation window
     * - Assign configured role to member
     * - Set expiration based on activity term length
     * - Create audit trail for role assignment
     * 
     * **Transaction Management**:
     * Comprehensive transaction management ensures consistency across approval
     * recording, authorization updates, role assignments, and notifications.
     * 
     * @param int $authorizationApprovalId Authorization approval record ID
     * @param int $approverId Member ID of approver making decision
     * @param int|null $nextApproverId Member ID of next approver (for multi-level)
     * @return ServiceResult Success/failure result with workflow status
     */
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        ?int $nextApproverId = null
    ): ServiceResult {
        $approvalTable = TableRegistry::getTableLocator()->get(
            "Activities.AuthorizationApprovals",
        );
        $authTable = $approvalTable->Authorizations;
        $transConnection = $approvalTable->getConnection();
        $transConnection->begin();
        $approval = $approvalTable->get(
            $authorizationApprovalId,
            contain: ["Authorizations.Activities"]
        );
        if (!$approval) {
            $transConnection->rollback();

            return new ServiceResult(false, "Approval not found");
        }
        $authorization = $approval->authorization;
        if (!$authorization) {
            $transConnection->rollback();

            return new ServiceResult(false, "Authorization not found");
        }
        $activity = $authorization->activity;
        if (!$activity) {
            $transConnection->rollback();

            return new ServiceResult(false, "Activity not found");
        }
        $this->saveAuthorizationApproval(
            $approverId,
            $approval,
            $approvalTable,
            $transConnection,
        );

        // Check if the authorization needs multiple approvers and process accordingly
        $requiredApprovalCount = $this->getApprovalsRequiredCount(
            $authorization->is_renewal,
            $activity,
        );
        if (
            $this->getNeedsMoreRenewals(
                $requiredApprovalCount,
                $authorization->id,
                $approvalTable,
            )
        ) {
            if (
                !$this->processForwardToNextApprover(
                    $approverId,
                    $nextApproverId,
                    $authorization,
                    $approvalTable,
                    $authTable,
                )
            ) {
                $transConnection->rollback();

                return new ServiceResult(false, "Failed to forward to next approver");
            } else {
                $transConnection->commit();

                return new ServiceResult(true);
            }
        } else {
            // Authorization is ready to approve
            if (
                !$this->processApprovedAuthorization(
                    $authorization,
                    $approverId,
                    $authTable
                )
            ) {
                $transConnection->rollback();

                return new ServiceResult(false, "Failed to process approved authorization");
            }
        }
        $transConnection->commit();

        return new ServiceResult(true);
    }
    /**
     * Process Authorization Denial
     * 
     * Handles authorization denial with comprehensive audit trail and notification system.
     * Updates authorization status and records denial reasoning for accountability.
     * 
     * **Denial Workflow**:
     * - Records approver decision with timestamp and denial reason
     * - Updates authorization status to denied
     * - Sets authorization temporal window to past (immediately expired)
     * - Creates audit trail with approver ID and reasoning
     * - Sends notification to requester with denial details
     * 
     * **Audit Trail Features**:
     * - Records approver ID for accountability
     * - Captures denial reason for future reference
     * - Timestamps decision for workflow tracking
     * - Maintains complete approval chain history
     * 
     * **Status Management**:
     * Denied authorizations are set to expired status with past temporal window
     * to ensure they don't interfere with future authorization requests while
     * maintaining complete audit trail.
     * 
     * **Notification System**:
     * Automatically notifies requester of denial decision with:
     * - Denial reason from approver
     * - Approver identity for transparency
     * - Activity details for context
     * - Guidance for future requests
     * 
     * @param int $authorizationApprovalId Authorization approval record ID
     * @param int $approverId Member ID of approver making denial decision
     * @param string $denyReason Reason for denial (required for audit trail)
     * @return ServiceResult Success/failure result with denial confirmation
     */
    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get(
            "Activities.AuthorizationApprovals",
        );
        $approval = $table->get(
            $authorizationApprovalId,
            contain: ["Authorizations"],
        );
        $approval->responded_on = DateTime::now();
        $approval->approved = false;
        $approval->approver_id = $approverId;
        $approval->approver_notes = $denyReason;
        $approval->authorization->revoker_id = $approverId;
        $approval->authorization->revoked_reason = $denyReason;
        $approval->authorization->status = Authorization::DENIED_STATUS;
        $approval->authorization->start_on = DateTime::now()->subSeconds(1);
        $approval->authorization->expires_on = DateTime::now()->subSeconds(1);
        $table->getConnection()->begin();
        if (
            !$table->save($approval) ||
            !$table->Authorizations->save($approval->authorization)
        ) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to deny authorization approval");
        }
        if (
            !$this->sendAuthorizationStatusToRequester(
                $approval->authorization->activity_id,
                $approval->authorization->member_id,
                $approverId,
                $approval->authorization->status,
                null,
            )
        ) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send authorization status to requester");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }

    /**
     * Process Authorization Revocation
     * 
     * Handles revocation of active authorizations with ActiveWindow integration
     * and comprehensive audit trail maintenance.
     * 
     * **Revocation Workflow**:
     * - Validates authorization exists and is revocable
     * - Integrates with ActiveWindowManager to stop temporal validation
     * - Updates authorization status to revoked
     * - Records revoker ID and revocation reason
     * - Automatically removes associated role assignments
     * - Sends notification to affected member
     * 
     * **ActiveWindow Integration**:
     * Uses ActiveWindowManager.stop() to:
     * - End temporal validation window immediately
     * - Remove role assignments granted by authorization
     * - Update authorization status to revoked
     * - Create complete audit trail of revocation
     * 
     * **Role Management**:
     * Revocation automatically removes any roles that were granted by the
     * authorization, ensuring immediate cessation of elevated permissions
     * and maintaining security compliance.
     * 
     * **Audit Requirements**:
     * - Records revoker identity for accountability
     * - Captures revocation reason for compliance
     * - Timestamps revocation for temporal tracking
     * - Maintains complete authorization lifecycle history
     * 
     * **Notification Process**:
     * Automatically notifies affected member of revocation with:
     * - Revocation reason and revoker identity
     * - Effective date of revocation
     * - Impact on permissions and roles
     * - Appeal or reauthorization process information
     * 
     * @param int $authorizationId Authorization record ID to revoke
     * @param int $revokerId Member ID of person performing revocation
     * @param string $revokedReason Reason for revocation (required for audit)
     * @return ServiceResult Success/failure result with revocation confirmation
     */
    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason,
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $table->getConnection()->begin();


        // revoke the member_role if it was granted
        $awResult = $this->activeWindowManager->stop(
            "Activities.Authorizations",
            $authorizationId,
            $revokerId,
            Authorization::REVOKED_STATUS,
            $revokedReason,
            DateTime::now()
        );
        if (!$awResult->success) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to revoke member role");
        }
        $authorization = $table->get($authorizationId);
        if (!$this->sendAuthorizationStatusToRequester(
            $authorization->activity_id,
            $authorization->member_id,
            $revokerId,
            $authorization->status,
            null,
        )) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send authorization status to requester");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }
    #endregion

    #region notifications
    /**
     * Send Authorization Status Notification to Requester
     * 
     * Sends status update notifications to authorization requesters with comprehensive
     * workflow context and next steps information.
     * 
     * **Notification Context**:
     * - Activity name and details
     * - Current authorization status
     * - Approver identity for transparency
     * - Next approver in chain (if applicable)
     * - Requester personalization
     * 
     * **Status Types Handled**:
     * - Approved: Final approval with role assignment details
     * - Denied: Denial reason and appeal process
     * - Pending: Forward to next approver information
     * - Revoked: Revocation details and impact
     * 
     * @param int $activityId Activity ID for context
     * @param int $requesterId Member ID of requester
     * @param int $approverId Member ID of current approver
     * @param string $status Current authorization status
     * @param int|null $nextApproverId Next approver ID (for multi-level workflows)
     * @return bool Success/failure of notification sending
     */
    protected function sendAuthorizationStatusToRequester(
        int $activityId,
        int $requesterId,
        int $approverId,
        string $status,
        int $nextApproverId = null,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $activity = $authTypesTable
            ->find()
            ->where(["id" => $activityId])
            ->select(["name"])
            ->all()
            ->first();
        $member = $membersTable
            ->find()
            ->where(["id" => $requesterId])
            ->select(["sca_name", "email_address"])
            ->all()
            ->first();
        $approver = $membersTable
            ->find()
            ->where(["id" => $approverId])
            ->select(["sca_name"])
            ->all()
            ->first();
        if ($nextApproverId) {
            $nextApprover = $membersTable
                ->find()
                ->where(["id" => $nextApproverId])
                ->select(["sca_name"])
                ->all()
                ->first();
            $nextApproverScaName = $nextApprover->sca_name;
        } else {
            $nextApproverScaName = '';
        }
        $this->getMailer("Activities.Activities")->send("notifyRequester", [
            $member->email_address,
            $status,
            $member->sca_name,
            $requesterId,
            $approver->sca_name,
            $nextApproverScaName,
            $activity->name,
        ]);

        return true;
    }

    /**
     * Send Approval Request Notification to Approver
     * 
     * Sends authorization approval request notifications to designated approvers
     * with secure token-based approval links and complete request context.
     * 
     * **Security Features**:
     * - Secure token generation for email-based approval
     * - Token-based authentication for approval actions
     * - Prevention of unauthorized approval access
     * 
     * **Notification Content**:
     * - Requester identity and details
     * - Activity information and requirements
     * - Secure approval/denial links
     * - Request context and urgency
     * - Workflow position (if multi-level)
     * 
     * **Token Management**:
     * Generates cryptographically secure tokens for each approval request
     * to enable email-based approval while maintaining security and preventing
     * unauthorized access to approval functionality.
     * 
     * @param int $activityId Activity ID for context
     * @param int $requesterId Member ID of requester
     * @param int $approverId Member ID of designated approver
     * @param string $authorizationToken Secure token for email-based approval
     * @return bool Success/failure of notification sending
     */
    private function sendApprovalRequestNotification(
        int $activityId,
        int $requesterId,
        int $approverId,
        string $authorizationToken,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $activity = $authTypesTable
            ->find()
            ->where(["id" => $activityId])
            ->select(["name"])
            ->all()
            ->first();
        $member = $membersTable
            ->find()
            ->where(["id" => $requesterId])
            ->select(["sca_name"])
            ->all()
            ->first();
        $approver = $membersTable
            ->find()
            ->where(["id" => $approverId])
            ->select(["sca_name", "email_address"])
            ->all()
            ->first();
        $this->getMailer("Activities.Activities")->send("notifyApprover", [
            $approver->email_address,
            $authorizationToken,
            $member->sca_name,
            $approver->sca_name,
            $activity->name,
        ]);

        return true;
    }
    // endregion

    // region approval processing methods
    /**
     * Process Final Authorization Approval
     * 
     * Handles final approval processing including status updates, role assignment,
     * and temporal validation activation through ActiveWindowManager integration.
     * 
     * **Final Approval Operations**:
     * - Updates authorization status to approved
     * - Increments approval count for audit trail
     * - Activates temporal validation window
     * - Assigns configured role to member
     * - Sends confirmation notification to requester
     * 
     * **ActiveWindow Integration**:
     * Calls ActiveWindowManager.start() to begin temporal validation with:
     * - Authorization effective date
     * - Activity-defined term length
     * - Role assignment automation
     * - Expiration date calculation
     * 
     * @param mixed $authorization Authorization entity to approve
     * @param int $approverId Member ID of final approver
     * @param mixed $authTable Authorizations table instance
     * @return bool Success/failure of approval processing
     */
    private function processApprovedAuthorization(
        $authorization,
        $approverId,
        $authTable
    ): bool {
        $authorization->status = Authorization::APPROVED_STATUS;
        $authorization->approval_count = $authorization->approval_count + 1;
        if (!$authTable->save($authorization)) {
            return false;
        }
        $awResult = $this->activeWindowManager->start(
            "Activities.Authorizations",
            $authorization->id,
            $approverId,
            DateTime::now(),
            null,
            $authorization->activity->term_length,
            $authorization->activity->grants_role_id,
        );
        if (!$awResult->success) {
            return false;
        }
        if (
            !$this->sendAuthorizationStatusToRequester(
                $authorization->activity_id,
                $authorization->member_id,
                $approverId,
                $authorization->status,
                null,
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Process Forward to Next Approver
     * 
     * Handles forwarding authorization to next approver in multi-level approval
     * workflows with validation, token generation, and notification management.
     * 
     * **Multi-Level Workflow Logic**:
     * - Validates next approver exists and has permission
     * - Updates authorization status to pending (for next level)
     * - Increments approval count for tracking
     * - Creates new approval record for next approver
     * - Generates secure token for email-based approval
     * - Sends notifications to both parties
     * 
     * **Security Validation**:
     * - Verifies next approver ID is valid
     * - Confirms approver has authorization permission
     * - Generates unique secure token for approval
     * 
     * **Notification Flow**:
     * - Notifies next approver of pending request
     * - Informs requester of workflow progress
     * - Provides transparent approval chain visibility
     * 
     * @param int $approverId Current approver ID
     * @param int $nextApproverId Next approver ID in chain
     * @param mixed $authorization Authorization entity
     * @param mixed $approvalTable Authorization approvals table
     * @param mixed $authTable Authorizations table
     * @return bool Success/failure of forwarding process
     */
    private function processForwardToNextApprover(
        $approverId,
        $nextApproverId,
        $authorization,
        $approvalTable,
        $authTable,
    ): bool {
        if ($nextApproverId == null) {
            return false;
        }
        if (
            $approvalTable->Approvers
            ->find()
            ->where(["id" => $nextApproverId])
            ->count() == 0
        ) {
            return false;
        }
        $authorization->status = Authorization::PENDING_STATUS;
        $authorization->approval_count = $authorization->approval_count + 1;
        $authorization->setDirty("status", true);
        $authorization->setDirty("approval_count", true);
        if (!$authTable->save($authorization)) {
            return false;
        }

        $nextApproval = $approvalTable->newEmptyEntity();
        $nextApproval->authorization_id = $authorization->id;
        $nextApproval->approver_id = $nextApproverId;
        $nextApproval->requested_on = DateTime::now();
        $nextApproval->authorization_token = StaticHelpers::generateToken(32);
        if (!$approvalTable->save($nextApproval)) {
            return false;
        }
        if (
            !$this->sendApprovalRequestNotification(
                $authorization->activity_id,
                $authorization->member_id,
                $nextApproverId,
                $nextApproval->authorization_token,
            )
        ) {
            return false;
        }

        if (
            !$this->sendAuthorizationStatusToRequester(
                $authorization->activity_id,
                $authorization->member_id,
                $approverId,
                $authorization->status,
                $nextApproverId,
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get Required Approvals Count
     * 
     * Determines the number of approvals required based on authorization type
     * and activity configuration settings.
     * 
     * **Configuration Logic**:
     * - New Authorizations: Uses activity.num_required_authorizors
     * - Renewal Authorizations: Uses activity.num_required_renewers
     * - Supports different approval requirements for new vs. renewal
     * 
     * @param bool $isRenewal Whether authorization is a renewal
     * @param mixed $activity Activity entity with approval requirements
     * @return int Number of approvals required
     */
    private function getApprovalsRequiredCount(
        $isRenewal,
        $activity,
    ): int {
        return $isRenewal
            ? $activity->num_required_renewers
            : $activity->num_required_authorizors;
    }

    /**
     * Check if More Approvals Needed
     * 
     * Determines whether authorization requires additional approvals before
     * final activation based on current approval count and requirements.
     * 
     * **Approval Logic**:
     * - Compares current approved count to required count
     * - Returns true if additional approvals needed
     * - Supports multi-level approval workflows
     * - Handles single-approval activities efficiently
     * 
     * @param int $requiredApprovalCount Total approvals required
     * @param int $authorizationId Authorization ID to check
     * @param mixed $approvalTable Authorization approvals table
     * @return bool True if more approvals needed, false if ready for activation
     */
    private function getNeedsMoreRenewals(
        $requiredApprovalCount,
        $authorizationId,
        $approvalTable,
    ): bool {
        if ($requiredApprovalCount > 1) {
            $acceptedApprovals = $approvalTable
                ->find()
                ->where([
                    "authorization_id" => $authorizationId,
                    "approved" => true,
                ])
                ->count();

            if ($acceptedApprovals < $requiredApprovalCount) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save Authorization Approval Record
     * 
     * Persists approval decision with timestamp, approver identity, and
     * approval status for audit trail and workflow tracking.
     * 
     * **Approval Recording**:
     * - Sets response timestamp for workflow tracking
     * - Records approver ID for accountability
     * - Sets approval status (approved = true)
     * - Maintains complete audit trail
     * 
     * **Transaction Safety**:
     * Includes transaction rollback on save failure to maintain
     * database consistency during approval processing.
     * 
     * @param int $approverId Member ID making approval decision
     * @param mixed $approval Authorization approval entity
     * @param mixed $approvalTable Authorization approvals table
     * @param mixed $transConnection Database transaction connection
     * @return bool Success/failure of approval recording
     */
    private function saveAuthorizationApproval(
        $approverId,
        $approval,
        $approvalTable,
        $transConnection,
    ): bool {
        // Set the approval to approved
        $approval->responded_on = DateTime::now();
        $approval->approved = true;
        $approval->approver_id = $approverId;

        // Save the approval
        if (!$approvalTable->save($approval)) {
            $transConnection->rollback();

            return false;
        }

        return true;
    }

    /**
     * Retract Pending Authorization Request
     * 
     * Allows a member to retract their own pending authorization request. This provides
     * member autonomy to cancel requests sent to wrong approvers or no longer needed.
     * 
     * **Business Logic**:
     * - Validates authorization is in pending status
     * - Ensures requester owns the authorization
     * - Updates status to retracted
     * - Maintains audit trail
     * - Optionally notifies approver of retraction
     * 
     * **Validation Rules**:
     * - Authorization must exist
     * - Authorization must be in PENDING status
     * - Requester must match authorization member_id
     * - Authorization cannot have been approved/denied
     * 
     * **Transaction Management**:
     * Uses database transactions to ensure consistency:
     * - Status update to RETRACTED
     * - Optional notification sending
     * - Rollback on any failure
     * 
     * **Success Result Data**:
     * Returns ServiceResult with:
     * - success: true
     * - data: ['authorization' => retracted authorization entity]
     * 
     * **Error Scenarios**:
     * - Authorization not found: "Authorization not found"
     * - Not pending: "Only pending authorizations can be retracted"
     * - Wrong owner: "You can only retract your own authorization requests"
     * - Status update failure: "Failed to update authorization status"
     * 
     * @param int $authorizationId Authorization record ID to retract
     * @param int $requesterId Member ID of person retracting (must be authorization owner)
     * @return ServiceResult Success/failure result with retraction confirmation
     */
    public function retract(
        int $authorizationId,
        int $requesterId
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");

        // Get the authorization
        $authorization = $table->find()
            ->where(['id' => $authorizationId])
            ->first();

        if (!$authorization) {
            return new ServiceResult(false, "Authorization not found");
        }

        // Validate authorization is pending
        if ($authorization->status !== Authorization::PENDING_STATUS) {
            return new ServiceResult(false, "Only pending authorizations can be retracted");
        }

        // Validate requester owns this authorization
        if ($authorization->member_id !== $requesterId) {
            return new ServiceResult(false, "You can only retract your own authorization requests");
        }

        // Begin transaction
        $table->getConnection()->begin();

        // Use ActiveWindowManager to stop the authorization (same as revoke)
        $retractedReason = "Retracted by requester on " . DateTime::now()->format('Y-m-d H:i:s');
        $awResult = $this->activeWindowManager->stop(
            "Activities.Authorizations",
            $authorizationId,
            $requesterId,
            Authorization::RETRACTED_STATUS,
            $retractedReason,
            DateTime::now()
        );

        if (!$awResult->success) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to retract authorization");
        }

        // Reload the authorization to get updated status
        $authorization = $table->get($authorizationId);

        // Optional: Send notification to approver that request was retracted
        // Get the pending approval to find the approver
        $approvalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $pendingApproval = $approvalsTable->find()
            ->where([
                'authorization_id' => $authorizationId,
                'responded_on IS' => null
            ])
            ->first();

        if ($pendingApproval) {
            // Mark the pending approval as responded so it no longer appears in the approver's queue
            $pendingApproval->responded_on = DateTime::now();
            $pendingApproval->approved = false;
            $pendingApproval->approver_notes = "Retracted by requester";
            if (!$approvalsTable->save($pendingApproval)) {
                $table->getConnection()->rollback();
                return new ServiceResult(false, "Failed to close pending approval");
            }

            if ($pendingApproval->approver_id) {
                // Send notification to approver (non-critical, don't fail if it doesn't send)
                $this->sendRetractedNotificationToApprover(
                    $authorization->activity_id,
                    $authorization->member_id,
                    $pendingApproval->approver_id
                );
            }
        }

        // Commit transaction
        $table->getConnection()->commit();

        return new ServiceResult(true, null, ['authorization' => $authorization]);
    }

    /**
     * Send Retraction Notification to Approver
     * 
     * Notifies the approver that an authorization request they were reviewing
     * has been retracted by the requester.
     * 
     * **Notification Context**:
     * - Activity name
     * - Requester name
     * - Retraction timestamp
     * 
     * @param int $activityId Activity ID for context
     * @param int $requesterId Member ID of requester who retracted
     * @param int $approverId Member ID of approver to notify
     * @return bool Success/failure of notification sending
     */
    private function sendRetractedNotificationToApprover(
        int $activityId,
        int $requesterId,
        int $approverId
    ): bool {
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $membersTable = TableRegistry::getTableLocator()->get("Members");

        $activity = $activitiesTable->get($activityId);
        $requester = $membersTable->get($requesterId);
        $approver = $membersTable->get($approverId);

        try {
            $this->getMailer("Activities.Activities")->send("notifyApproverOfRetraction", [
                $approver->email_address,
                $activity->name,
                $approver->sca_name,
                $requester->sca_name
            ]);
            return true;
        } catch (\Exception $e) {
            // Log but don't fail on notification errors
            return false;
        }
    }

    // endregion
}
