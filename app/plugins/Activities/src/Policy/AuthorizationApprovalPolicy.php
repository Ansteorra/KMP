<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApproval;
use Activities\Model\Table\ActivitiesTable;
use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Authorization Approval Policy for Multi-Level Approval Workflows
 *
 * This policy class defines authorization rules for AuthorizationApproval entity operations within the KMP Activities plugin.
 * It provides comprehensive access control for multi-level approval workflows, approval queue management, and authorization
 * approval processes with activity-specific permission validation and approver authority verification.
 *
 * **Purpose:**
 * - Controls access to AuthorizationApproval entity operations through activity-specific permission validation
 * - Manages multi-level approval workflows with approver authority verification
 * - Provides approval queue access control for individual approvers and administrative oversight
 * - Integrates with KMP's RBAC system for activity-specific approval authority validation
 *
 * **Authorization Architecture:**
 * - **Activity-Based Authorization**: Approval authority based on activity-specific permission requirements
 * - **Approver Validation**: Integration with ActivitiesTable for approver authority verification
 * - **Queue-Based Access**: Individual approver access to their own approval queues
 * - **Administrative Oversight**: Permission-based access for approval workflow management
 *
 * **Key Features:**
 * - **Activity-Specific Approval Authority**: Validates approver permissions for specific activities
 * - **Multi-Level Workflow Support**: Manages complex approval chains with role-based authority
 * - **Queue Management**: Individual and administrative access to approval queues
 * - **Approval Chain Security**: Prevents unauthorized approval operations and workflow manipulation
 * - **Integration with Permission System**: Activity-specific permission validation for approval authority
 *
 * **Approval Authority Validation:**
 * - Uses `ActivitiesTable::canAuthorizeActivity()` for activity-specific permission checking
 * - Validates approver authority based on activity permission requirements
 * - Supports role-based approval authority with warrant validation
 * - Integrates with branch scoping for organizational approval boundaries
 *
 * **Access Control Patterns:**
 * - **Individual Approver Access**: Direct access to own approval queue and assigned approvals
 * - **Activity-Based Authority**: Permission validation for activity-specific approval operations
 * - **Administrative Oversight**: Permission-based access for approval workflow management
 * - **Queue Security**: Restricted access to approval queues based on approver assignment
 *
 * **Integration Points:**
 * - **ActivitiesTable**: Activity-specific approval authority validation
 * - **PermissionsLoader**: Core permission validation and warrant checking
 * - **BasePolicy**: Inherited permission discovery and policy framework integration
 * - **Authorization Workflow**: Multi-level approval process management and validation
 *
 * **Security Considerations:**
 * - Approval authority validation prevents unauthorized approval operations
 * - Activity-specific permission checking ensures proper qualification for approval roles
 * - Queue access control maintains privacy and workflow integrity
 * - Integration with warrant system provides temporal validation for approval authority
 *
 * @see \App\Policy\BasePolicy Parent policy with core RBAC functionality
 * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity
 * @see \Activities\Model\Table\ActivitiesTable Activity-specific approval authority validation
 * @see \Activities\Controller\AuthorizationApprovalsController Approval workflow management
 */

class AuthorizationApprovalPolicy extends BasePolicy
{
    /**
     * Check if the user can approve authorization requests.
     *
     * Determines approval authority for authorization requests based on activity-specific permission requirements
     * and approver qualification validation through the Activities table authorization system.
     *
     * **Authorization Logic:**
     * - Validates activity-specific approval authority through `ActivitiesTable::canAuthorizeActivity()`
     * - Resolves activity ID from authorization relationship for permission checking
     * - Ensures approver has appropriate permissions for the specific activity type
     * - Integrates with KMP's RBAC system for warrant-based temporal validation
     *
     * **Activity-Specific Validation:**
     * - Each activity has specific permission requirements for approval authority
     * - Approvers must have appropriate role assignments and warrant validation
     * - Permission checking includes branch scoping and organizational boundaries
     * - Supports complex approval hierarchies with role-based authority
     *
     * **Authorization Resolution:**
     * 1. Attempts to resolve activity ID from contained authorization relationship
     * 2. Falls back to database lookup if relationship not loaded
     * 3. Validates approver authority for the specific activity through ActivitiesTable
     * 4. Returns boolean result based on permission and warrant validation
     *
     * **Permission Requirements:**
     * Activity-specific approval authority typically includes:
     * - Role assignments with approval authority for the activity type
     * - Valid warrants for the approval role during the authorization request period
     * - Branch-scoped permissions matching organizational approval boundaries
     * - Activity-specific permission requirements defined in activity configuration
     *
     * **Usage Examples:**
     * ```php
     * // Controller authorization for approval interface
     * $this->Authorization->authorize($authorizationApproval, 'approve');
     * 
     * // Service-level approval authority validation
     * if ($this->Authorization->can($user, 'approve', $authorizationApproval)) {
     *     $authorizationManager->approve($approval->id, $user->id, $nextApproverId);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Approval authority validation prevents unauthorized approval operations
     * - Activity-specific permission checking ensures qualified approvers only
     * - Integration with warrant system provides temporal validation for approval roles
     * - Database fallback ensures robust authorization resolution
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $entity The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can approve the authorization request, false otherwise
     * @see \Activities\Model\Table\ActivitiesTable::canAuthorizeActivity() Activity-specific approval authority validation
     */
    function canApprove(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $authorization_id = $entity->authorization_id;
        $authorization = $entity->authorization;
        $activity_id = null;
        if ($authorization) {
            $activity_id = $authorization->activity_id;
        }
        if (!$activity_id) {
            $activity_id = TableRegistry::getTableLocator()
                ->get("Activities.Authorizations")
                ->get($authorization_id)->activity_id;
        }
        return ActivitiesTable::canAuthorizeActivity($user, $activity_id);
    }

    /**
     * Check if the user can deny authorization requests.
     *
     * Determines denial authority for authorization requests based on activity-specific permission requirements
     * and approver qualification validation through the Activities table authorization system.
     *
     * **Authorization Logic:**
     * - Validates activity-specific approval authority through `ActivitiesTable::canAuthorizeActivity()`
     * - Uses identical authorization logic to approve() method for consistent permission validation
     * - Resolves activity ID from authorization relationship for permission checking
     * - Ensures approver has appropriate permissions for the specific activity type
     *
     * **Activity-Specific Validation:**
     * - Denial authority follows same permission requirements as approval authority
     * - Approvers must have appropriate role assignments and warrant validation
     * - Permission checking includes branch scoping and organizational boundaries
     * - Supports complex approval hierarchies with role-based denial authority
     *
     * **Authorization Resolution:**
     * 1. Attempts to resolve activity ID from contained authorization relationship
     * 2. Falls back to database lookup if relationship not loaded
     * 3. Validates approver authority for the specific activity through ActivitiesTable
     * 4. Returns boolean result based on permission and warrant validation
     *
     * **Usage Examples:**
     * ```php
     * // Controller authorization for denial interface
     * $this->Authorization->authorize($authorizationApproval, 'deny');
     * 
     * // Service-level denial authority validation
     * if ($this->Authorization->can($user, 'deny', $authorizationApproval)) {
     *     $authorizationManager->deny($approval->id, $user->id, $reason);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Denial authority validation prevents unauthorized denial operations
     * - Uses same permission validation as approval for consistent security model
     * - Integration with warrant system provides temporal validation for denial authority
     * - Maintains audit trail for denial operations and accountability
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $entity The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can deny the authorization request, false otherwise
     * @see \Activities\Model\Table\ActivitiesTable::canAuthorizeActivity() Activity-specific denial authority validation
     */
    function canDeny(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $authorization_id = $entity->authorization_id;
        $authorization = $entity->authorization;
        $activity_id = null;
        if ($authorization) {
            $activity_id = $authorization->activity_id;
        }
        if (!$activity_id) {
            $activity_id = TableRegistry::getTableLocator()
                ->get("Activities.Authorizations")
                ->get($authorization_id)->activity_id;
        }
        return ActivitiesTable::canAuthorizeActivity($user, $activity_id);
    }

    /**
     * Check if the user can view authorization approval records.
     *
     * Determines view access for authorization approval records based on dual authorization model
     * combining approver ownership and administrative permission validation.
     *
     * **Authorization Logic:**
     * 1. **Approver Access**: Direct access for assigned approvers to their approval records
     * 2. **Administrative Access**: Permission-based access for approval workflow oversight
     * 3. **Permission Validation**: Delegates to BasePolicy for administrative viewing permissions
     *
     * **Approver-Based Access:**
     * - Direct access when `entity->approver_id` matches requesting user's ID
     * - Enables approvers to view their assigned approval records and queue
     * - Supports approver workflow management and decision tracking
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.viewApprovals": Authority to view approval records and workflows
     * - "Activities.manageApprovals": Administrative approval workflow management
     * - "Activities.auditApprovals": Access to approval audit trails and analytics
     * - Branch-scoped permissions: Organizational boundaries for approval visibility
     *
     * **Usage Examples:**
     * ```php
     * // Approver viewing assigned approval
     * $this->Authorization->authorize($authorizationApproval, 'view'); // Returns true for assigned approver
     * 
     * // Administrative approval record review
     * if ($this->Authorization->can($user, 'view', $authorizationApproval)) {
     *     $approvalDetails = $this->getApprovalDetailsWithHistory($approval->id);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Approver access maintains workflow privacy and assigned responsibility
     * - Administrative access requires appropriate permission validation
     * - Integration with audit trail for approval record access tracking
     * - Prevents unauthorized access to sensitive approval workflow information
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval|\Cake\ORM\Table $entity The authorization approval entity or table
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can view the authorization approval, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $entity->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can access their own authorization approval queue.
     *
     * Determines access to personal approval queue interface based on general approval authority
     * validation through the Activities table authorization system.
     *
     * **Authorization Logic:**
     * - Validates user has approval authority for any activity type
     * - Uses `ActivitiesTable::canAuhtorizeAnyActivity()` for general approval capability checking
     * - Provides access to personal approval queue dashboard and management interface
     * - Supports approver workflow management and queue prioritization
     *
     * **Queue Access Control:**
     * - Personal queue access requires general approval authority for any activity
     * - Prevents unauthorized access to approval workflow interfaces
     * - Enables efficient approval queue management for qualified approvers
     * - Supports cross-activity approval authority validation
     *
     * **Approval Authority Validation:**
     * - Checks for any activity approval permissions through Activities table
     * - Validates role assignments with approval authority across activity types
     * - Includes warrant validation for temporal approval authority
     * - Supports branch-scoped approval authority for organizational boundaries
     *
     * **Usage Examples:**
     * ```php
     * // Queue access authorization in controller
     * $this->Authorization->authorize($this->AuthorizationApprovals->newEmptyEntity(), 'myQueue');
     * 
     * // Service-level queue access validation
     * if ($this->Authorization->can($user, 'myQueue', $approvalEntity)) {
     *     $queueItems = $this->getPersonalApprovalQueue($user->id);
     * }
     * ```
     *
     * **Queue Management Features:**
     * - Personal approval queue dashboard access
     * - Cross-activity approval workflow management
     * - Approval priority and timeline management
     * - Integration with notification and reminder systems
     *
     * **Security Considerations:**
     * - Queue access restricted to users with actual approval authority
     * - Prevents unauthorized access to approval workflow interfaces
     * - Integration with audit trail for queue access tracking
     * - Maintains workflow security and approver accountability
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $entity The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can access their approval queue, false otherwise
     * @see \Activities\Model\Table\ActivitiesTable::canAuhtorizeAnyActivity() General approval authority validation
     */
    public function canMyQueue(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    /**
     * Check if the user can view a list of available approvers for authorization workflows.
     *
     * Determines access to approver discovery interface based on dual authorization model
     * combining approver ownership and administrative permission validation.
     *
     * **Authorization Logic:**
     * 1. **Assigned Approver Access**: Direct access for assigned approvers to discover alternative approvers
     * 2. **Administrative Access**: Permission-based access for approval workflow management
     * 3. **Permission Validation**: Delegates to BasePolicy for administrative approver discovery permissions
     *
     * **Approver-Based Access:**
     * - Direct access when user is the assigned approver for the authorization
     * - Enables approver reassignment and workflow delegation capabilities
     * - Supports approval workflow flexibility and backup approver discovery
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.manageApprovers": Authority to view and manage approver assignments
     * - "Activities.manageApprovals": Administrative approval workflow management
     * - "Activities.assignApprovers": Permission for approver assignment and reassignment
     * - Branch-scoped permissions: Organizational boundaries for approver discovery
     *
     * **Approver Discovery Features:**
     * - List of qualified approvers for specific activity types
     * - Approver availability and workload information
     * - Alternative approver suggestions for workflow management
     * - Integration with approval authority validation
     *
     * **Usage Examples:**
     * ```php
     * // Assigned approver discovering alternatives
     * $this->Authorization->authorize($authorizationApproval, 'availableApproversList'); // Returns true for assigned approver
     * 
     * // Administrative approver management
     * if ($this->Authorization->can($user, 'availableApproversList', $authorizationApproval)) {
     *     $availableApprovers = $this->getQualifiedApprovers($approval->authorization->activity_id);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Approver discovery maintains workflow flexibility while ensuring qualified alternatives
     * - Administrative access requires appropriate permission validation
     * - Prevents unauthorized access to approver information and workflow details
     * - Integration with approval authority validation for accurate approver lists
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $approval The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can view available approvers list, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    function canAvailableApproversList(KmpIdentityInterface $user, $approval): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $approval->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $approval);
    }

    /**
     * Check if the user can approve authorization requests via mobile interface.
     *
     * Determines approval authority for authorization requests in mobile workflows based on 
     * activity-specific permission requirements. Uses identical authorization logic to approve()
     * method for consistent permission validation across desktop and mobile interfaces.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $entity The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can approve the authorization request via mobile, false otherwise
     * @see \Activities\Policy\AuthorizationApprovalPolicy::canApprove() Desktop approval authorization
     */
    function canMobileApprove(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canApprove($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if the user can deny authorization requests via mobile interface.
     *
     * Determines denial authority for authorization requests in mobile workflows based on 
     * activity-specific permission requirements. Uses identical authorization logic to deny()
     * method for consistent permission validation across desktop and mobile interfaces.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\AuthorizationApproval $entity The authorization approval entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can deny the authorization request via mobile, false otherwise
     * @see \Activities\Policy\AuthorizationApprovalPolicy::canDeny() Desktop denial authorization
     */
    function canMobileDeny(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canDeny($user, $entity, ...$optionalArgs);
    }
}
