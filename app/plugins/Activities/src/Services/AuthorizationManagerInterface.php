<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * AuthorizationManagerInterface - Core Authorization Business Logic Service Contract
 *
 * This interface defines the contract for managing member activity authorizations within
 * the Kingdom Management Portal. It provides a comprehensive service layer for authorization
 * workflows including requests, approvals, denials, and lifecycle management.
 *
 * ## Service Architecture
 *
 * ### Transaction Management
 * All service methods require external transaction management for data consistency:
 * - **Transactional Context**: All operations must be wrapped in database transactions
 * - **Rollback Support**: Failed operations can be safely rolled back
 * - **Atomic Operations**: Complex authorization workflows handled atomically
 * - **Data Integrity**: Ensures referential integrity across authorization entities
 *
 * ### ServiceResult Pattern
 * All methods return ServiceResult objects providing:
 * - **Success/Failure Status**: Clear indication of operation outcome
 * - **Error Messages**: Detailed error information for debugging and user feedback
 * - **Return Data**: Structured data for successful operations
 * - **Validation Results**: Field-level validation feedback
 *
 * ## Core Authorization Operations
 *
 * ### Authorization Request Workflow
 * - **request()**: Initiates new authorization requests for activities
 * - **Member Eligibility**: Validates member meets activity requirements
 * - **Approver Assignment**: Routes requests to appropriate approvers
 * - **Initial Status**: Sets authorization to pending status
 *
 * ### Approval Management
 * - **approve()**: Processes authorization approvals from qualified approvers
 * - **Multi-Level Approval**: Supports complex approval workflows
 * - **Validation**: Ensures approver has permission to authorize activity
 * - **Activation**: Activates authorization when requirements met
 *
 * ### Denial and Rejection
 * - **deny()**: Processes authorization denials with reasoning
 * - **Audit Trail**: Maintains complete record of denial reasons
 * - **Notification**: Supports notification of denial to relevant parties
 *
 * ## Integration Requirements
 *
 * ### ActiveWindowManager Integration
 * Authorization lifecycle management depends on ActiveWindowManager for:
 * - **Temporal Management**: Start and end date handling for authorization periods
 * - **Expiration Processing**: Automatic expiration of time-bounded authorizations
 * - **Renewal Workflows**: Managing authorization renewal cycles
 * - **Historical Tracking**: Maintaining authorization history
 *
 * ### RBAC Integration
 * Authorization operations integrate with KMP's RBAC system:
 * - **Permission Validation**: Ensures approvers have appropriate permissions
 * - **Branch Scoping**: Respects organizational boundaries
 * - **Role Granting**: Automatically grants roles when configured
 *
 * ## Usage Examples
 *
 * ### Authorization Request
 * ```php
 * // Controller implementation
 * $connectionManager->begin();
 * try {
 *     $result = $authorizationManager->request(
 *         $member->id,
 *         $activity->id,
 *         $selectedApprover->id,
 *         false // not a renewal
 *     );
 *     
 *     if ($result->isSuccess()) {
 *         $connectionManager->commit();
 *         $this->Flash->success('Authorization request submitted successfully');
 *     } else {
 *         $connectionManager->rollback();
 *         $this->Flash->error($result->getErrorMessage());
 *     }
 * } catch (Exception $e) {
 *     $connectionManager->rollback();
 *     throw $e;
 * }
 * ```
 *
 * ### Authorization Approval
 * ```php
 * // Process approval with potential next approver
 * $connectionManager->begin();
 * try {
 *     $result = $authorizationManager->approve(
 *         $authorizationApprovalId,
 *         $currentUser->id,
 *         $nextApproverId // null if no additional approval needed
 *     );
 *     
 *     if ($result->isSuccess()) {
 *         $connectionManager->commit();
 *         $authorizationData = $result->getData();
 *         if ($authorizationData['activated']) {
 *             $this->Flash->success('Authorization approved and activated');
 *         } else {
 *             $this->Flash->success('Approval recorded, awaiting additional approvals');
 *         }
 *     }
 * } catch (Exception $e) {
 *     $connectionManager->rollback();
 *     throw $e;
 * }
 * ```
 *
 * ### Authorization Denial
 * ```php
 * // Process denial with reason
 * $result = $authorizationManager->deny(
 *     $authorizationApprovalId,
 *     $currentUser->id,
 *     'Insufficient experience for this activity level'
 * );
 * 
 * if ($result->isSuccess()) {
 *     $this->Flash->success('Authorization request denied');
 *     // Send notification to requester
 * }
 * ```
 *
 * ## Implementation Requirements
 *
 * ### Data Validation
 * Implementations must validate:
 * - **Member Eligibility**: Age requirements, prerequisites, existing authorizations
 * - **Approver Authority**: Permission to authorize specific activities
 * - **Business Rules**: Activity-specific authorization requirements
 * - **Temporal Constraints**: Authorization period validity
 *
 * ### Error Handling
 * Implementations should handle:
 * - **Invalid Parameters**: Graceful handling of invalid input data
 * - **Permission Errors**: Clear messaging for authorization failures
 * - **Database Errors**: Proper exception handling and transaction rollback
 * - **Business Rule Violations**: Detailed validation feedback
 *
 * ### Performance Considerations
 * Implementations should optimize for:
 * - **Efficient Queries**: Minimize database operations for authorization workflows
 * - **Caching Integration**: Leverage existing permission and member caching
 * - **Batch Operations**: Support efficient processing of multiple authorizations
 *
 * @see \Activities\Services\DefaultAuthorizationManager Default implementation
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface Temporal management dependency
 * @see \App\Services\ServiceResult Return type for all operations
 * @see \Activities\Model\Entity\Authorization Authorization entity
 * @see \Activities\Model\Entity\AuthorizationApproval Approval workflow entity
 * @package Activities\Services
 * @since KMP 1.0
 */
interface AuthorizationManagerInterface
{
    /**
     * Approve Authorization Request
     *
     * Processes approval of an authorization request by an authorized approver. This method
     * handles the approval workflow including validation, approval recording, and potential
     * authorization activation when all required approvals are collected.
     *
     * ## Approval Workflow Logic
     *
     * ### Approver Validation
     * - **Permission Check**: Validates approver has permission to authorize activity
     * - **Self-Approval Prevention**: Prevents members from approving their own requests
     * - **Branch Scope Validation**: Ensures approval within appropriate organizational boundaries
     * - **Duplicate Prevention**: Prevents multiple approvals from same approver
     *
     * ### Multi-Level Approval Support
     * - **Required Approvals**: Checks if authorization meets required approval count
     * - **Next Approver Assignment**: Routes to additional approvers when needed
     * - **Final Activation**: Activates authorization when all requirements met
     * - **Role Granting**: Automatically grants configured roles upon activation
     *
     * ### Transaction Requirements
     * This method requires external transaction management:
     * ```php
     * $connection->begin();
     * try {
     *     $result = $authorizationManager->approve($approvalId, $approverId, $nextApproverId);
     *     if ($result->isSuccess()) {
     *         $connection->commit();
     *     } else {
     *         $connection->rollback();
     *     }
     * } catch (Exception $e) {
     *     $connection->rollback();
     *     throw $e;
     * }
     * ```
     *
     * @param int $authorizationApprovalId ID of authorization approval record to process
     * @param int $approverId ID of member providing approval
     * @param int|null $nextApproverId Optional ID of next required approver in workflow
     * @return ServiceResult Success/failure with authorization data or error details
     * @throws \InvalidArgumentException When approval ID or approver ID is invalid
     * @throws \UnauthorizedException When approver lacks permission to authorize activity
     * @since KMP 1.0
     */
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null
    ): ServiceResult;

    /**
     * Deny Authorization Request
     *
     * Processes denial of an authorization request with detailed reasoning. This method
     * records the denial, updates authorization status, and maintains audit trail for
     * organizational accountability and feedback.
     *
     * ## Denial Processing Logic
     *
     * ### Approver Validation
     * - **Permission Check**: Validates approver has authority to deny authorization
     * - **Status Validation**: Ensures authorization is in valid state for denial
     * - **Branch Scope**: Validates denial within appropriate organizational context
     *
     * ### Audit Trail Management
     * - **Denial Recording**: Records detailed denial reasoning
     * - **Status Update**: Updates authorization status to denied
     * - **Timestamp Tracking**: Maintains denial timestamp for audit purposes
     * - **User Accountability**: Links denial to specific approver
     *
     * ### Notification Integration
     * - **Denial Notification**: Supports notification of denial to relevant parties
     * - **Reason Communication**: Provides denial reasoning for feedback
     * - **Appeal Process**: Maintains data for potential appeal workflows
     *
     * @param int $authorizationApprovalId ID of authorization approval record to deny
     * @param int $approverId ID of member providing denial
     * @param string $denyReason Detailed reason for authorization denial
     * @return ServiceResult Success/failure with denial confirmation or error details
     * @throws \InvalidArgumentException When approval ID or approver ID is invalid
     * @throws \UnauthorizedException When approver lacks permission to deny authorization
     * @since KMP 1.0
     */
    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): ServiceResult;

    /**
     * Request Activity Authorization
     *
     * Initiates a new authorization request for a member to participate in a specific
     * activity. This method handles the complete request workflow including validation,
     * approver assignment, and initial request creation.
     *
     * ## Request Validation Logic
     *
     * ### Member Eligibility
     * - **Age Requirements**: Validates member meets activity age restrictions
     * - **Prerequisites**: Checks for required prerequisite authorizations
     * - **Existing Authorizations**: Prevents duplicate authorization requests
     * - **Status Validation**: Ensures member is in good standing
     *
     * ### Activity Configuration
     * - **Activity Status**: Validates activity is active and accepting requests
     * - **Approval Requirements**: Identifies required number of approvers
     * - **Permission Mapping**: Links to permission required for authorization
     * - **Role Configuration**: Identifies roles granted upon approval
     *
     * ### Approver Assignment
     * - **Approver Validation**: Ensures assigned approver has required permissions
     * - **Branch Scoping**: Validates approver within appropriate organizational scope
     * - **Conflict Prevention**: Prevents self-approval scenarios
     * - **Workflow Routing**: Sets up multi-level approval workflow when required
     *
     * ### Renewal Support
     * - **Renewal Flag**: Distinguishes renewal requests from initial requests
     * - **Expiration Extension**: Calculates new expiration dates for renewals
     * - **Simplified Workflow**: May use streamlined approval process for renewals
     * - **Historical Tracking**: Maintains link to previous authorization
     *
     * @param int $requesterId ID of member requesting authorization
     * @param int $activityId ID of activity for which authorization is requested
     * @param int $approverId ID of approver assigned to process request
     * @param bool $isRenewal Whether this is a renewal of existing authorization
     * @return ServiceResult Success/failure with authorization request data or error details
     * @throws \InvalidArgumentException When requester, activity, or approver ID is invalid
     * @throws \BusinessRuleException When request violates business rules (age, prerequisites, etc.)
     * @since KMP 1.0
     */
    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal,
    ): ServiceResult;

    /**
     * Revoke Active Authorization
     *
     * Revokes an active authorization with detailed reasoning and audit trail. This method
     * handles administrative revocation of authorizations due to policy violations,
     * safety concerns, or other organizational requirements.
     *
     * ## Revocation Processing Logic
     *
     * ### Authorization Validation
     * - **Status Check**: Validates authorization is active and revokable
     * - **Authority Validation**: Ensures revoker has permission to revoke authorization
     * - **Business Rules**: Applies organization-specific revocation rules
     * - **Dependency Checking**: Identifies dependent authorizations or roles
     *
     * ### Revocation Execution
     * - **Status Update**: Changes authorization status to revoked
     * - **Effective Date**: Sets revocation effective date (immediate or future)
     * - **Role Removal**: Removes associated roles when configured
     * - **Cascade Handling**: Manages dependent authorization impacts
     *
     * ### Audit Trail Management
     * - **Revocation Recording**: Records detailed revocation reasoning
     * - **User Accountability**: Links revocation to specific administrator
     * - **Timestamp Tracking**: Maintains revocation timestamp for audit purposes
     * - **Appeal Process**: Maintains data for potential appeal workflows
     *
     * ### ActiveWindow Integration
     * Uses ActiveWindowManager for:
     * - **Temporal Management**: Handles authorization end date updates
     * - **Status Transitions**: Manages authorization lifecycle state changes
     * - **Historical Preservation**: Maintains authorization history integrity
     *
     * @param int $authorizationId ID of authorization to revoke
     * @param int $revokerId ID of administrator performing revocation
     * @param string $revokedReason Detailed reason for authorization revocation
     * @return ServiceResult Success/failure with revocation confirmation or error details
     * @throws \InvalidArgumentException When authorization ID or revoker ID is invalid
     * @throws \UnauthorizedException When revoker lacks permission to revoke authorization
     * @throws \BusinessRuleException When revocation violates business rules
     * @since KMP 1.0
     */
    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason
    ): ServiceResult;
}
