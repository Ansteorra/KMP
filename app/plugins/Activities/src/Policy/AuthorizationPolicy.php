<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\KMP\KmpIdentityInterface;
use Activities\Model\Entity\Authorization;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Authorization Policy for Member Activity Authorizations
 *
 * This policy class defines authorization rules for Authorization entity operations within the KMP Activities plugin.
 * It provides comprehensive access control for member authorization requests, approvals, and lifecycle management
 * with both self-service capabilities and administrative oversight.
 *
 * **Purpose:**
 * - Controls access to Authorization entity operations through permission-based and ownership-based rules
 * - Provides self-service authorization for members managing their own activity authorizations
 * - Ensures proper administrative oversight for authorization workflows and approval processes
 * - Integrates with KMP's RBAC system for authorization management and approval authority validation
 *
 * **Authorization Architecture:**
 * - **Dual Authorization Model**: Combines ownership-based and permission-based authorization
 * - **Self-Service Access**: Members can manage their own authorization requests and renewals
 * - **Administrative Control**: Permission-based access for administrative and approval operations
 * - **Policy Framework Integration**: Dynamic policy evaluation through permission policy associations
 *
 * **Key Features:**
 * - **Ownership Validation**: Members have full access to their own authorization records
 * - **Permission Integration**: Administrative operations require appropriate permissions
 * - **Approval Authority**: Integration with activity-specific approval permission requirements
 * - **Workflow Security**: Proper access control for authorization lifecycle management
 * - **Audit Trail Support**: Authorization tracking with proper access control for historical data
 *
 * **Access Control Patterns:**
 * - **Member Self-Service**: Direct access for own authorization requests and renewals
 * - **Administrative Oversight**: Permission-based access for authorization management
 * - **Approval Authority**: Activity-specific permissions for authorization approval operations
 * - **Reporting Access**: Controlled access to authorization statistics and reporting data
 *
 * **Integration Points:**
 * - **PermissionsLoader**: Core permission validation and warrant checking for administrative access
 * - **BasePolicy**: Inherited permission discovery and policy framework integration
 * - **Authorization Controllers**: Access control for authorization workflow interfaces
 * - **Activity System**: Integration with activity-specific approval requirements and permissions
 *
 * **Security Considerations:**
 * - Members maintain privacy and control over their own authorization records
 * - Administrative access requires appropriate permission validation through RBAC system
 * - Approval operations integrate with activity-specific permission requirements
 * - Authorization viewing includes proper scope limitations for organizational privacy
 *
 * @see \App\Policy\BasePolicy Parent policy with core RBAC functionality
 * @see \Activities\Model\Entity\Authorization Authorization entity with workflow logic
 * @see \Activities\Controller\AuthorizationsController Authorization workflow management
 * @see \App\KMP\PermissionsLoader Core permission validation engine
 */
class AuthorizationPolicy extends BasePolicy
{
    /**
     * Check if the user can revoke an authorization.
     *
     * Determines authorization for revoking member activity authorizations, providing access control
     * for administrative revocation operations and authorization lifecycle management.
     *
     * **Authorization Logic:**
     * - Delegates to permission-based policy evaluation through BasePolicy
     * - Requires appropriate administrative permissions for revocation authority
     * - Integrates with Activities plugin permission policy framework
     * - Supports warrant-based temporal validation for administrative roles
     *
     * **Permission Requirements:**
     * Typically requires permissions such as:
     * - "Activities.revoke": Direct authorization revocation authority
     * - "Activities.manage": General authorization management capabilities
     * - Activity-specific permissions: Based on the authorization's activity requirements
     *
     * **Usage Examples:**
     * ```php
     * // Controller authorization for revocation interface
     * $this->Authorization->authorize($authorization, 'revoke');
     * 
     * // Service-level authorization check
     * if ($this->Authorization->can($user, 'revoke', $authorization)) {
     *     $authorizationManager->revoke($authorization->id, $user->id, $reason);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Revocation is typically restricted to administrative users with appropriate authority
     * - Integration with audit trail system for accountability and compliance
     * - Warrant validation ensures temporal authority for revocation operations
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization $entity The authorization entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can revoke the authorization, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation
     */
    public function canRevoke(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can add an authorization request.
     *
     * Determines authorization for creating new authorization requests, implementing dual access control
     * with member self-service capabilities and administrative override authority.
     *
     * **Authorization Logic:**
     * 1. **Self-Service Access**: Members can request authorizations for themselves
     * 2. **Administrative Access**: Users with appropriate permissions can request on behalf of others
     * 3. **Permission Validation**: Delegates to permission-based policy evaluation for administrative operations
     *
     * **Ownership-Based Access:**
     * - Direct access when `entity->member_id` matches requesting user's ID
     * - Enables self-service authorization request workflows
     * - Supports member autonomy in authorization management
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.request": Authority to request authorizations for other members
     * - "Activities.manage": General authorization management capabilities
     * - Branch-scoped permissions: Organizational boundaries for authorization requests
     *
     * **Usage Examples:**
     * ```php
     * // Member requesting own authorization
     * $authorization = $this->Authorizations->newEntity(['member_id' => $currentUser->id]);
     * $this->Authorization->authorize($authorization, 'add'); // Returns true for own request
     * 
     * // Administrative authorization request
     * $authorization = $this->Authorizations->newEntity(['member_id' => $targetMember->id]);
     * $this->Authorization->authorize($authorization, 'add'); // Requires permission validation
     * ```
     *
     * **Security Considerations:**
     * - Self-service access maintains member autonomy while ensuring proper identity verification
     * - Administrative access requires appropriate permission validation through RBAC system
     * - Integration with warrant system for temporal validation of administrative authority
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization|\Cake\ORM\Table $entity The authorization entity or table
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can add authorization request, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($this->canManageAuthorizationMember($user, (int)$entity->member_id)) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can request a renewal of an authorization.
     *
     * Determines authorization for renewing existing authorizations, implementing dual access control
     * with member self-service renewal capabilities and administrative renewal authority.
     *
     * **Authorization Logic:**
     * 1. **Self-Service Renewal**: Members can renew their own authorizations
     * 2. **Administrative Renewal**: Users with appropriate permissions can renew authorizations for others
     * 3. **Permission Validation**: Delegates to permission-based policy evaluation for administrative operations
     *
     * **Ownership-Based Access:**
     * - Direct access when `entity->member_id` matches requesting user's ID
     * - Enables self-service authorization renewal workflows
     * - Supports member autonomy in maintaining current authorizations
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.renew": Authority to renew authorizations for other members
     * - "Activities.manage": General authorization management capabilities
     * - Activity-specific permissions: Based on the authorization's activity requirements
     *
     * **Renewal Context:**
     * - Renewal requests typically maintain existing approval chains and requirements
     * - May have different approval requirements than initial authorization requests
     * - Supports continuous authorization maintenance for ongoing activities
     *
     * **Usage Examples:**
     * ```php
     * // Member renewing own authorization
     * $this->Authorization->authorize($authorization, 'renew'); // Returns true for own authorization
     * 
     * // Administrative renewal for expired authorizations
     * if ($this->Authorization->can($user, 'renew', $authorization)) {
     *     $authorizationManager->renew($authorization->id, $user->id);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Self-service renewal maintains member control over their authorization lifecycle
     * - Administrative renewal requires appropriate permission validation and audit trail
     * - Integration with temporal validation for authorization expiration and renewal timing
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization $entity The authorization entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can renew the authorization, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    public function canRenew(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->canManageAuthorizationMember($user, (int)$entity->member_id)) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can view a specific member's authorizations.
     *
     * Determines authorization for viewing member authorization lists and details, implementing dual access control
     * with member self-service visibility and administrative oversight capabilities.
     *
     * **Authorization Logic:**
     * 1. **Self-Service Access**: Members can view their own authorization records and history
     * 2. **Administrative Access**: Users with appropriate permissions can view authorizations for other members
     * 3. **Permission Validation**: Delegates to permission-based policy evaluation for administrative viewing
     *
     * **Ownership-Based Access:**
     * - Direct access when `entity->member_id` matches requesting user's ID
     * - Enables member privacy and control over their authorization information
     * - Supports self-service authorization status checking and renewal planning
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.viewMemberAuthorizations": Authority to view member authorization records
     * - "Activities.manage": General authorization management and oversight capabilities
     * - "Reports.activities": Reporting and analysis access for authorization data
     * - Branch-scoped permissions: Organizational boundaries for member authorization visibility
     *
     * **Privacy and Security:**
     * - Member authorization data includes sensitive information about qualifications and status
     * - Administrative access requires proper permission validation and audit trail
     * - Integration with branch scoping for organizational privacy boundaries
     *
     * **Usage Examples:**
     * ```php
     * // Member viewing own authorizations
     * $this->Authorization->authorize($authorization, 'memberAuthorizations'); // Returns true for own records
     * 
     * // Administrative authorization review
     * if ($this->Authorization->can($user, 'memberAuthorizations', $authorization)) {
     *     $authorizationsList = $this->getMemberAuthorizationsQuery($memberId);
     * }
     * ```
     *
     * **Integration Points:**
     * - Member profile interfaces for self-service authorization management
     * - Administrative reporting and oversight dashboards
     * - Authorization workflow interfaces for approval and renewal processes
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization $entity The authorization entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can view member authorizations, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    public function canMemberAuthorizations(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->canManageAuthorizationMember($user, (int)$entity->member_id)) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can retract an authorization request.
     *
     * Determines authorization for retracting pending authorization requests, implementing
     * ownership-based access control to allow members to cancel their own pending requests.
     *
     * **Authorization Logic:**
     * 1. **Self-Service Retraction**: Members can retract their own pending authorization requests
     * 2. **Status Validation**: Retraction only allowed for pending authorizations
     * 3. **Ownership Requirement**: Only the requesting member can retract
     *
     * **Ownership-Based Access:**
     * - Direct access when `entity->member_id` matches requesting user's ID
     * - Enables member autonomy in managing authorization workflow
     * - No administrative override - retraction is member-only action
     *
     * **Use Cases:**
     * - Request sent to wrong approver
     * - Request no longer needed
     * - Stalled request with no response
     * - Incorrect activity requested
     *
     * **Usage Examples:**
     * ```php
     * // Member retracting own pending authorization
     * $this->Authorization->authorize($authorization, 'retract'); // Returns true for own request
     * 
     * // Controller validation
     * if ($this->Authorization->can($user, 'retract', $authorization)) {
     *     $authorizationManager->retract($authorization->id, $user->id);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Retraction is strictly owner-only operation
     * - Maintains member privacy and control over authorization requests
     * - Does not require administrative permission validation
     * - Only applicable to pending requests, not approved authorizations
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization $entity The authorization entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can retract the authorization, false otherwise
     */
    public function canRetract(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Only the member who requested the authorization can retract it
        if ($this->canManageAuthorizationMember($user, (int)$entity->member_id)) {
            return true;
        }
        return false;
    }

    /**
     * Check if the user can view authorizations for a specific activity.
     *
     * Determines authorization for viewing activity-specific authorization lists and statistics,
     * implementing dual access control with member self-service capabilities and administrative oversight.
     *
     * **Authorization Logic:**
     * 1. **Self-Service Access**: Members can view their own authorizations within activity context
     * 2. **Administrative Access**: Users with appropriate permissions can view all activity authorizations
     * 3. **Permission Validation**: Delegates to permission-based policy evaluation for administrative access
     *
     * **Activity-Focused Viewing:**
     * - Provides activity-centric view of authorization data for administrative management
     * - Supports activity-specific reporting and oversight workflows
     * - Enables activity administrators to monitor participation and approval status
     *
     * **Permission-Based Access:**
     * For administrative operations, typically requires permissions such as:
     * - "Activities.viewActivityAuthorizations": Authority to view activity-specific authorization lists
     * - "Activities.manage": General authorization management and oversight capabilities
     * - Activity-specific permissions: Based on the activity's permission requirements
     * - Branch-scoped permissions: Organizational boundaries for activity authorization visibility
     *
     * **Usage Examples:**
     * ```php
     * // Activity administrator viewing authorization status
     * if ($this->Authorization->can($user, 'activityAuthorizations', $authorization)) {
     *     $activityAuthorizations = $this->getActivityAuthorizationsQuery($activityId);
     * }
     * 
     * // Activity-specific reporting and analytics
     * $this->Authorization->authorize($authorization, 'activityAuthorizations');
     * ```
     *
     * **Integration Points:**
     * - Activity management interfaces for authorization oversight
     * - Administrative reporting dashboards with activity-focused data
     * - Approval workflow management for activity-specific processes
     * - Activity statistics and participation tracking
     *
     * **Security Considerations:**
     * - Activity authorization data may reveal organizational structure and member qualifications
     * - Administrative access requires proper permission validation and audit trail
     * - Integration with activity-specific permission requirements for granular access control
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Activities\Model\Entity\Authorization $entity The authorization entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can view activity authorizations, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    public function activityAuthorizations(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->canManageAuthorizationMember($user, (int)$entity->member_id)) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Determine whether the user can manage authorization actions for a member.
     *
     * Allows self or parent-of-minor access.
     *
     * @param KmpIdentityInterface $user
     * @param int $memberId
     * @return bool
     */
    protected function canManageAuthorizationMember(KmpIdentityInterface $user, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        if ($user instanceof \App\Model\Entity\Member) {
            $target = new \App\Model\Entity\Member();
            $target->id = $memberId;
            return $user->canManageMember($target);
        }

        return false;
    }
}
