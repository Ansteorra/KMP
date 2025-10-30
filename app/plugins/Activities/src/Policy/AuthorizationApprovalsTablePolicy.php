<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Table\AuthorizationApprovalsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Activities\Model\Table\ActivitiesTable;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;

/**
 * Authorization Approvals Table Authorization Policy
 *
 * This policy class defines authorization rules for AuthorizationApprovals table operations within the KMP Activities plugin.
 * It extends the BasePolicy to inherit core RBAC functionality while providing table-level authorization logic
 * for approval queue management, bulk operations, and administrative access to the authorization approvals data layer.
 *
 * ## Purpose
 *
 * - **Table-Level Authorization**: Controls access to AuthorizationApprovals table operations and approval queue management
 * - **Query Scoping**: Applies authorization filters to approval queries based on user permissions and approver assignments
 * - **Queue Management**: Governs access to personal and administrative approval queues
 * - **Administrative Operations**: Provides bulk operations and administrative oversight for approval workflows
 * - **Approval Authority Integration**: Seamless integration with activity-specific approval authority validation
 *
 * ## Authorization Architecture
 *
 * The policy leverages the BasePolicy framework and Activities table integration to provide:
 * - **Approver-Based Scoping**: Personal queue access based on approver assignments
 * - **Permission-Based Access**: Administrative operations through KMP's permission system
 * - **Activity Authority Integration**: Approval authority validation through ActivitiesTable
 * - **Queue Security**: Restricted access to approval queues based on approver qualification
 * - **Administrative Oversight**: Enhanced permissions for cross-approver queue management
 *
 * ## Table Operations Governed
 *
 * ### Queue Management
 * - **Personal Queue Access**: Individual approver access to assigned approval items
 * - **Administrative Queues**: Permission-based access to all approval queues
 * - **Queue Analytics**: Statistical analysis and reporting for approval workflows
 * - **Bulk Operations**: Mass approval operations and queue management
 *
 * ### Query Scoping
 * - **Index Queries**: Approval listing with appropriate scope limitations
 * - **Search Operations**: Permission-based filtering for approval discovery
 * - **Reporting Queries**: Administrative access to approval analytics and metrics
 * - **Audit Operations**: Historical approval data access and compliance reporting
 *
 * ## Security Implementation
 *
 * ### Permission Requirements
 * Table operations typically require permissions such as:
 * - **General Approval Authority**: Basic qualification for any approval operations
 * - **"Activities.manageApprovals"**: Administrative approval workflow management
 * - **"Activities.viewAllQueues"**: Access to all approval queues across approvers
 * - **"Activities.auditApprovals"**: Access to approval audit trails and compliance data
 * - **"Activities.bulkApprove"**: Permission for bulk approval operations
 *
 * ### Authorization Patterns
 * ```php
 * // Personal queue access
 * $myQueue = $this->Authorization->applyScope($approvalsQuery, 'myQueue');
 * 
 * // Administrative queue access with full scope
 * if ($this->Authorization->can($user, 'allQueues', $approvalsTable)) {
 *     $allQueues = $this->Authorization->applyScope($approvalsQuery, 'index');
 * }
 * ```
 *
 * ## Integration Points
 *
 * - **BasePolicy**: Inherits core RBAC functionality and permission validation
 * - **ActivitiesTable**: Activity-specific approval authority validation
 * - **AuthorizationApprovalsController**: Queue management interface authorization
 * - **Approval Workflow**: Multi-level approval process management and validation
 * - **Reporting System**: Table-level authorization for approval analytics
 *
 * ## Queue Scoping Logic
 *
 * The policy implements sophisticated scoping logic:
 * - **Personal Scope**: Limits access to approver's assigned approval items
 * - **Administrative Scope**: Full access for users with appropriate permissions
 * - **Activity-Based Filtering**: Integration with activity-specific approval authority
 * - **Organizational Boundaries**: Branch-scoped approval queue access
 *
 * ## Performance Considerations
 *
 * - **Database-Level Filtering**: Authorization scopes applied at query level for efficiency
 * - **Permission Caching**: Leverages security cache for repeated authorization checks
 * - **Selective Loading**: Optimized queries based on user approval authority scope
 * - **Index Support**: Authorization filters designed to work with database indexes
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality and permission validation
 * @see \Activities\Model\Table\AuthorizationApprovalsTable For approval data layer operations
 * @see \Activities\Controller\AuthorizationApprovalsController For queue management interface
 * @see \Activities\Model\Table\ActivitiesTable For activity-specific approval authority validation
 */
class AuthorizationApprovalsTablePolicy extends BasePolicy
{
    /**
     * Check if the user can access their personal approval queue.
     *
     * Determines access to personal approval queue interface based on general approval authority
     * validation through the Activities table authorization system.
     *
     * **Authorization Logic:**
     * - Validates user has approval authority for any activity type
     * - Uses `ActivitiesTable::canAuhtorizeAnyActivity()` for general approval capability checking
     * - Provides access to personal approval queue management interface
     * - Supports approver workflow management and queue prioritization
     *
     * **Queue Access Control:**
     * - Personal queue access requires general approval authority for any activity
     * - Prevents unauthorized access to approval workflow interfaces
     * - Enables efficient approval queue management for qualified approvers
     * - Supports cross-activity approval authority validation
     *
     * **Usage Examples:**
     * ```php
     * // Personal queue access authorization
     * $this->Authorization->authorize($approvalsTable, 'myQueue');
     * 
     * // Conditional queue access in services
     * if ($this->Authorization->can($user, 'myQueue', $approvalsTable)) {
     *     $personalQueue = $this->getPersonalApprovalQueue($user->id);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Queue access restricted to users with actual approval authority
     * - Prevents unauthorized access to approval workflow interfaces
     * - Integration with audit trail for queue access tracking
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The table entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can access their approval queue, false otherwise
     * @see \Activities\Model\Table\ActivitiesTable::canAuhtorizeAnyActivity() General approval authority validation
     */
    public function canMyQueue(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    /**
     * Check if the user can access all approval queues administratively.
     *
     * Determines administrative access to all approval queues across all approvers based on
     * permission validation through the BasePolicy framework.
     *
     * **Authorization Logic:**
     * - Delegates to permission-based policy evaluation through BasePolicy
     * - Requires administrative permissions for cross-approver queue access
     * - Provides oversight capability for approval workflow management
     * - Supports administrative analytics and queue management operations
     *
     * **Administrative Access:**
     * Typically requires permissions such as:
     * - "Activities.viewAllQueues": Direct access to all approval queues
     * - "Activities.manageApprovals": Administrative approval workflow management
     * - "Activities.auditApprovals": Access to approval audit trails and analytics
     * - Branch-scoped permissions: Organizational boundaries for queue oversight
     *
     * **Usage Examples:**
     * ```php
     * // Administrative queue access authorization
     * $this->Authorization->authorize($approvalsTable, 'allQueues');
     * 
     * // Conditional administrative access
     * if ($this->Authorization->can($user, 'allQueues', $approvalsTable)) {
     *     $allQueues = $this->getAllApprovalQueues();
     * }
     * ```
     *
     * **Administrative Features:**
     * - Cross-approver queue visibility and management
     * - Approval workflow analytics and reporting
     * - Queue assignment and approver management
     * - Bulk approval operations and oversight
     *
     * **Security Considerations:**
     * - Administrative access requires elevated permissions
     * - Integration with audit trail for administrative queue access
     * - Maintains approver privacy while enabling necessary oversight
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The table entity
     * @param mixed ...$optionalArgs Additional arguments for policy evaluation
     * @return bool True if user can access all approval queues, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicy() Core permission validation for administrative access
     */
    public function canAllQueues(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Apply authorization scope to index queries for approval listings.
     *
     * Implements sophisticated query scoping for approval index operations based on user
     * permissions and approver authority, providing appropriate data access limitations.
     *
     * **Scoping Logic:**
     * 1. **Administrative Access**: Users with "allQueues" permission see all approval records
     * 2. **Personal Scope**: Regular approvers see only their assigned approval items
     * 3. **Permission Validation**: Integrates with BasePolicy for administrative access checking
     *
     * **Query Modification:**
     * - **Full Access**: Returns unmodified query for administrative users
     * - **Approver Scope**: Adds `WHERE approver_id = :userId` filter for personal access
     * - **Performance Optimization**: Database-level filtering for efficient data access
     *
     * **Usage Examples:**
     * ```php
     * // Automatic scope application in controller
     * $approvals = $this->Authorization->applyScope($this->AuthorizationApprovals->find(), 'index');
     * 
     * // Manual scope application in services
     * $scopedQuery = $this->authorizationPolicy->scopeIndex($user, $baseQuery);
     * ```
     *
     * **Security Implementation:**
     * - Prevents unauthorized access to other approvers' queue items
     * - Maintains approval workflow privacy and security
     * - Enables administrative oversight when appropriate permissions exist
     * - Integrates with database indexing for performance optimization
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with appropriate access limitations
     * @see \Activities\Policy\AuthorizationApprovalsTablePolicy::canAllQueues() Administrative access validation
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        // Get the AuthorizationApprovalsTable for permission checking
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");

        // Get an empty instance for permission validation
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        // Check for administrative access to all queues
        if ($this->canAllQueues($user, $authorizationApproval)) {
            // Return unscoped query for administrative users
            return $query;
        } else {
            // Apply personal scope filter for regular approvers
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }

    /**
     * Apply personal approver scope to the provided query.
     *
     * Limits query results to approval items assigned to the given user.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user whose identifier will be matched against `approver_id`.
     * @param \Cake\ORM\Query $query The base query to apply the personal scope to.
     * @return \Cake\ORM\Query The query filtered so `approver_id` equals the user's identifier.
     */
    public function scopeMyQueue(KmpIdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Apply personal approver scope to queries used by the mobile approve authorizations interface.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user whose approver_id will be enforced.
     * @param \Cake\ORM\Query $query The base query to scope.
     * @return \Cake\ORM\Query The query filtered to records with approver_id equal to the user's identifier.
     */
    public function scopeMobileApproveAuthorizations(KmpIdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Apply authorization scope for the mobile approve action.
     *
     * Restricts the query to the requesting user's personal approvals (approver_id equals the user's identifier)
     * when the user does not have administrative access to all queues; returns the unmodified query otherwise.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user.
     * @param \Cake\ORM\Query $query The base query to scope.
     * @return \Cake\ORM\Query The scoped or unmodified query.
     */
    public function scopeMobileApprove(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Apply authorization scope to queries for the mobile deny action.
     *
     * Scopes the query to the requesting user's approvals when the user does not
     * have administrative access to all queues.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user.
     * @param \Cake\ORM\Query $query The base query to scope.
     * @return \Cake\ORM\Query The query scoped to the user's approver_id when the user lacks all-queues access, otherwise the unmodified query.
     */
    public function scopeMobileDeny(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Apply authorization scope to view queries for approval details.
     *
     * Implements sophisticated query scoping for approval detail view operations based on user
     * permissions and approver authority, providing appropriate data access for approval viewing.
     *
     * **Scoping Logic:**
     * 1. **Administrative Access**: Users with "allQueues" permission can view all approval details
     * 2. **Personal Scope**: Regular approvers can only view their assigned approval items
     * 3. **Permission Validation**: Integrates with BasePolicy for administrative access checking
     *
     * **Query Modification:**
     * - **Full Access**: Returns unmodified query for administrative users
     * - **Approver Scope**: Adds `WHERE approver_id = :userId` filter for personal access
     * - **Detail Security**: Ensures approval details remain private to assigned approvers
     *
     * **Usage Examples:**
     * ```php
     * // Approval detail view authorization
     * $approval = $this->Authorization->applyScope($this->AuthorizationApprovals->find(), 'view')
     *     ->where(['id' => $approvalId])->first();
     * 
     * // Service-level approval detail access
     * $approvalQuery = $this->authorizationPolicy->scopeView($user, $baseQuery);
     * ```
     *
     * **Security Implementation:**
     * - Prevents unauthorized access to approval details and workflow information
     * - Maintains approval confidentiality and approver responsibility
     * - Enables administrative oversight when appropriate permissions exist
     * - Protects sensitive approval workflow data and decision history
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with appropriate access limitations
     * @see \Activities\Policy\AuthorizationApprovalsTablePolicy::canAllQueues() Administrative access validation
     */
    public function scopeView(KmpIdentityInterface $user, $query)
    {
        // Get the AuthorizationApprovalsTable for permission checking
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");

        // Get an empty instance for permission validation
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        // Check for administrative access to all approval details
        if ($this->canAllQueues($user, $authorizationApproval)) {
            // Return unscoped query for administrative users
            return $query;
        } else {
            // Apply personal scope filter for regular approvers
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }
}