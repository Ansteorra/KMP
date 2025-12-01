<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use App\KMP\PermissionsLoader;
use App\Model\Entity\Member;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use App\Model\Entity\BaseEntity;

/**
 * Activity Entity - Activity Management and Authorization Requirements
 *
 * The Activity entity represents individual activities within the Kingdom Management Portal
 * that require member authorization for participation. Activities are organized into
 * hierarchical groups and define specific requirements for member participation including
 * age restrictions, authorization periods, and approval workflows.
 *
 * ## Core Activity Architecture
 *
 * ### Activity Hierarchy
 * Activities are organized within ActivityGroups providing logical categorization:
 * - **Combat Activities**: Heavy weapons, rapier, archery, thrown weapons
 * - **Arts & Sciences**: Research, teaching, judging, craft demonstrations
 * - **Service Activities**: Event management, administrative roles, coordination
 * - **Youth Activities**: Age-appropriate participation with special supervision
 *
 * ### Authorization Requirements
 * Each activity defines specific authorization parameters:
 * - **Term Length**: Duration of authorization validity in days
 * - **Age Restrictions**: Minimum/maximum age requirements for participation
 * - **Required Authorizers**: Number of approvers needed for authorization
 * - **Permission Requirements**: RBAC permission needed to authorize others
 * - **Role Grants**: Automatic role assignment upon authorization approval
 *
 * ## Database Schema Integration
 *
 * ### Core Fields
 * - **id**: Primary key for activity identification
 * - **name**: Unique activity name for display and reference
 * - **length**: Authorization validity period in days (virtual field from term_length)
 * - **activity_group_id**: Foreign key to ActivityGroup for categorization
 * - **minimum_age/maximum_age**: Age restrictions for participation eligibility
 * - **num_required_authorizors**: Number of approvers required for authorization
 * - **deleted**: Soft deletion timestamp for activity lifecycle management
 *
 * ### Integration Fields
 * - **permission_id**: Permission required to authorize this activity
 * - **grants_role_id**: Role automatically granted upon authorization approval
 * - **num_required_renewers**: Approvers needed for authorization renewal
 *
 * ## Entity Relationships
 *
 * ### Parent Relationships
 * - **ActivityGroup**: Categorization and grouping for activities
 * - **Permission**: RBAC permission required to authorize this activity
 * - **Role**: Role automatically granted to authorized members
 *
 * ### Child Relationships
 * - **Authorizations**: All authorization records for this activity
 * - **CurrentAuthorizations**: Active authorization records
 * - **PendingAuthorizations**: Authorization requests awaiting approval
 * - **UpcomingAuthorizations**: Future-dated authorization records
 * - **PreviousAuthorizations**: Historical authorization records
 *
 * ## Authorization Workflow Integration
 *
 * ### Approver Discovery
 * The entity provides methods to identify who can approve authorization requests:
 * - **getApproversQuery()**: Returns members with permission to authorize this activity
 * - **Permission-based authorization**: Uses PermissionsLoader for approver identification
 * - **Branch-scoped approval**: Approvers identified within organizational boundaries
 *
 * ### Business Logic Enforcement
 * - **Age Validation**: Automatic validation of member age against activity requirements
 * - **Authorization Limits**: Enforcement of authorization period lengths
 * - **Approval Requirements**: Validation of required number of authorizers
 *
 * ## Security Architecture
 *
 * ### Mass Assignment Protection
 * The entity protects against mass assignment vulnerabilities while allowing:
 * - Activity configuration (name, term_length, age restrictions)
 * - Relationship management (activity_group, permissions, roles)
 * - Authorization tracking (member_activities, pending_authorizations)
 * - Administrative fields (deleted, num_required_renewers)
 *
 * ### Permission Integration
 * - **Authorization Permission**: Links to Permission entity for approver identification
 * - **RBAC Integration**: Uses PermissionsLoader for security enforcement
 * - **Branch Scoping**: Respects organizational boundaries for approval authority
 *
 * ## Performance Considerations
 *
 * ### Query Optimization
 * - **Efficient Approver Lookup**: Uses PermissionsLoader for optimized permission queries
 * - **Relationship Loading**: Supports eager loading for related entities
 * - **Cache Integration**: Compatible with BaseEntity caching architecture
 *
 * ### Database Performance
 * - **Indexed Lookups**: Optimized for frequent activity and authorization queries
 * - **Soft Deletion**: Maintains referential integrity while supporting logical deletion
 * - **Foreign Key Optimization**: Efficient joins with related entities
 *
 * ## Usage Examples
 *
 * ### Activity Configuration
 * ```php
 * // Create new combat activity
 * $activity = $activitiesTable->newEntity([
 *     'name' => 'Heavy Weapons Authorization',
 *     'term_length' => 1095, // 3 years in days
 *     'activity_group_id' => $combatGroupId,
 *     'minimum_age' => 18,
 *     'maximum_age' => null,
 *     'num_required_authorizors' => 2,
 *     'permission_id' => $marshalPermissionId,
 *     'grants_role_id' => $fighterRoleId
 * ]);
 * ```
 *
 * ### Approver Discovery
 * ```php
 * // Find members who can authorize this activity
 * $approversQuery = $activity->getApproversQuery();
 * $approvers = $approversQuery->toArray();
 * 
 * // Get approvers within specific branch
 * $branchApprovers = $activity->getApproversQuery()
 *     ->where(['Members.branch_id' => $branchId])
 *     ->toArray();
 * ```
 *
 * ### Authorization Workflow
 * ```php
 * // Check if member meets age requirements
 * if ($member->age >= $activity->minimum_age && 
 *     ($activity->maximum_age === null || $member->age <= $activity->maximum_age)) {
 *     // Member eligible for authorization
 *     $authorizationManager->requestAuthorization($member, $activity, $requestData);
 * }
 * ```
 *
 * @property int $id Primary key
 * @property string $name Activity name
 * @property int $length Authorization validity period in days (virtual field from term_length)
 * @property int $term_length Authorization validity period in days
 * @property int $activity_group_id Foreign key to ActivityGroup
 * @property int|null $minimum_age Minimum age requirement
 * @property int|null $maximum_age Maximum age requirement
 * @property int $num_required_authorizors Number of approvers required
 * @property \Cake\I18n\Date|null $deleted Soft deletion timestamp
 *
 * @property \App\Model\Entity\ActivityGroup $activity_group Parent activity group
 * @property \App\Model\Entity\MemberActivity[] $member_activities Legacy member activities
 * @property \App\Model\Entity\PendingAuthorization[] $pending_authorizations Legacy pending authorizations
 * @property \App\Model\Entity\Permission[] $permissions Associated permissions
 *
 * @see \Activities\Model\Entity\ActivityGroup Parent categorization entity
 * @see \Activities\Model\Entity\Authorization Child authorization records
 * @see \App\Model\Entity\Permission RBAC permission integration
 * @see \App\Model\Entity\Role Role granting functionality
 * @see \Activities\Services\AuthorizationManagerInterface Authorization business logic
 * @package Activities\Model\Entity
 * @since KMP 1.0
 */
class Activity extends BaseEntity
{
    /**
     * Mass Assignment Configuration for Activity Entity
     *
     * Defines which fields can be safely mass assigned through newEntity() or patchEntity()
     * methods. This configuration balances security and usability by allowing access to
     * appropriate fields while protecting sensitive data.
     *
     * ## Accessible Fields
     *
     * ### Core Activity Configuration
     * - **name**: Activity display name and identifier
     * - **term_length**: Authorization validity period in days
     * - **activity_group_id**: Parent activity group for categorization
     * - **minimum_age/maximum_age**: Age restriction parameters
     * - **num_required_authorizors**: Number of approvers required for authorization
     * - **num_required_renewers**: Number of approvers required for renewal
     *
     * ### Administrative Controls
     * - **deleted**: Soft deletion timestamp for lifecycle management
     * - **permission_id**: Permission required to authorize this activity
     * - **grants_role_id**: Role automatically granted upon authorization
     *
     * ### Relationship Management
     * - **activity_group**: Parent ActivityGroup entity for categorization
     * - **role**: Role entity for automatic granting functionality
     * - **member_activities**: Legacy member activity associations
     * - **pending_authorizations**: Legacy pending authorization records
     *
     * ## Security Considerations
     * This configuration prevents mass assignment of:
     * - **id**: Primary key protection
     * - **created/modified**: Timestamp protection
     * - **computed fields**: Virtual fields derived from other data
     *
     * @var array<string, bool> Field accessibility configuration
     * @since KMP 1.0
     */
    protected array $_accessible = [
        "name" => true,
        "term_length" => true,
        "activity_group_id" => true,
        "minimum_age" => true,
        "maximum_age" => true,
        "num_required_authorizors" => true,
        "deleted" => true,
        "activity_group" => true,
        "member_activities" => true,
        "pending_authorizations" => true,
        "permission_id" => true,
        "grants_role_id" => true,
        "role" => true,
        "num_required_renewers" => true,
    ];

    /**
     * Virtual property: activity_group_name
     *
     * Returns the name of the associated activity group for grid display.
     *
     * @return string|null Activity group name or null if not loaded
     */
    protected function _getActivityGroupName(): ?string
    {
        if (isset($this->activity_group) && $this->activity_group) {
            return $this->activity_group->name;
        }

        return null;
    }

    /**
     * Virtual property: grants_role_name
     *
     * Returns the name of the role granted by this activity, or "None" if no role is granted.
     *
     * @return string Role name or "None"
     */
    protected function _getGrantsRoleName(): string
    {
        if (isset($this->role) && $this->role) {
            return $this->role->name;
        }

        return 'None';
    }

    /**
     * Get Approvers Query for Activity Authorization
     *
     * Returns a query object for finding members who have permission to authorize
     * this specific activity. This method leverages the PermissionsLoader system
     * to identify members with the appropriate permission within the specified
     * organizational branch scope.
     *
     * ## Authorization Logic
     *
     * ### Permission-Based Authorization
     * The method uses the activity's permission_id to identify who can authorize:
     * - **Permission Requirement**: Members must have the specific permission
     * - **Branch Scoping**: Authorization respects organizational boundaries
     * - **Active Status**: Only active members with valid permissions included
     *
     * ### Query Optimization
     * - **PermissionsLoader Integration**: Uses optimized permission validation queries
     * - **Cache Benefits**: Leverages existing permission caching infrastructure
     * - **Efficient Joins**: Minimizes database queries through proper relationship handling
     *
     * ## Usage Examples
     *
     * ### Basic Approver Discovery
     * ```php
     * // Get all potential approvers for this activity within branch
     * $approversQuery = $activity->getApproversQuery($member->branch_id);
     * $approvers = $approversQuery->toArray();
     * 
     * // Count available approvers
     * $approverCount = $approversQuery->count();
     * if ($approverCount < $activity->num_required_authorizors) {
     *     throw new InsufficientApproversException();
     * }
     * ```
     *
     * ### Advanced Filtering
     * ```php
     * // Get approvers with additional criteria
     * $seniorApprovers = $activity->getApproversQuery($branchId)
     *     ->innerJoinWith('MemberRoles.Roles', function ($q) {
     *         return $q->where(['Roles.name LIKE' => '%Senior%']);
     *     })
     *     ->distinct()
     *     ->toArray();
     * ```
     *
     * ### Authorization Workflow Integration
     * ```php
     * // Find approvers excluding the requesting member
     * $availableApprovers = $activity->getApproversQuery($member->branch_id)
     *     ->where(['Members.id !=' => $requestingMember->id])
     *     ->toArray();
     * 
     * foreach ($availableApprovers as $approver) {
     *     $authorizationManager->sendApprovalRequest($authorization, $approver);
     * }
     * ```
     *
     * ## Error Handling
     *
     * ### Permission Validation
     * The method validates that the activity has a properly configured permission:
     * ```php
     * if (!isset($this->permission_id)) {
     *     throw new \Exception("Permission ID not set");
     * }
     * ```
     *
     * ### Branch Validation
     * The branch_id parameter must be valid and represent an existing branch:
     * - Invalid branch_id will result in empty query results
     * - PermissionsLoader handles branch scoping validation internally
     *
     * ## Integration Points
     *
     * ### PermissionsLoader Integration
     * - **getMembersWithPermissionsQuery()**: Core permission validation method
     * - **Branch Scoping**: Automatic organizational boundary enforcement
     * - **Performance Optimization**: Leverages existing caching and query optimization
     *
     * ### Authorization Service Integration
     * - **Approval Workflows**: Used by AuthorizationManager for approval routing
     * - **Notification Systems**: Enables targeted notifications to eligible approvers
     * - **Administrative Tools**: Powers administrative interfaces for approver management
     *
     * @param int $branch_id Branch scope for approver identification
     * @return \Cake\ORM\Query\SelectQuery Query object for eligible approvers
     * @throws \Exception When permission_id is not configured for activity
     * @see \App\KMP\PermissionsLoader::getMembersWithPermissionsQuery() Core permission query method
     * @see \Activities\Services\AuthorizationManagerInterface Authorization business logic
     * @since KMP 1.0
     */
    public function getApproversQuery(Int $branch_id)
    {
        // Validate that activity has permission configuration
        if (!isset($this->permission_id)) {
            throw new \Exception("Permission ID not set");
        }

        // Use PermissionsLoader for optimized permission-based member query
        return PermissionsLoader::getMembersWithPermissionsQuery($this->permission_id, $branch_id);
    }
}
