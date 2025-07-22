<?php

declare(strict_types=1);

namespace App\Model\Entity;

use JeremyHarris\LazyLoad\ORM\LazyLoadEntityTrait;

/**
 * Role Entity - Core KMP RBAC Role Management
 *
 * The Role entity represents a security role within the KMP Role-Based Access Control (RBAC) system.
 * Roles serve as containers for permissions and are assigned to members through the MemberRole junction
 * entity, enabling flexible and time-bounded permission management across the organizational hierarchy.
 *
 * ## Core RBAC Architecture
 *
 * The KMP RBAC system follows a three-tier model:
 * - **Members**: Individual users who can be assigned roles
 * - **Roles**: Named collections of permissions (this entity)
 * - **Permissions**: Specific access rights and capabilities
 *
 * ## Key Features
 *
 * ### Role-Permission Association
 * - Many-to-many relationship with permissions through roles_permissions table
 * - Permissions define what actions a role can perform
 * - Supports both system-level and organizational permissions
 *
 * ### Member-Role Assignment
 * - Many-to-many relationship with members through MemberRole entity
 * - Assignments are time-bounded with start_on and expires_on dates
 * - Supports approval workflow with approver tracking
 * - Integrates with ActiveWindow system for temporal validity
 *
 * ### Role Naming and Organization
 * - Simple name-based identification (e.g., "Autocrat", "Seneschal", "Herald")
 * - Names must be unique across the system
 * - Descriptive naming follows SCA organizational structure
 *
 * ## Database Schema
 *
 * ### Primary Fields
 * - `id` (int): Primary key, auto-increment
 * - `name` (string): Unique role name, max 255 characters
 * - `created` (datetime): Audit timestamp
 * - `modified` (datetime): Audit timestamp
 * - `created_by` (int): Creator member ID (Footprint behavior)
 * - `modified_by` (int): Last modifier member ID (Footprint behavior)
 * - `deleted` (datetime): Soft delete timestamp (Trash behavior)
 *
 * ## Usage Examples
 *
 * ### Basic Role Operations
 * ```php
 * // Create a new role
 * $role = $rolesTable->newEntity([
 *     'name' => 'Event Steward'
 * ]);
 * $rolesTable->save($role);
 *
 * // Find role with permissions
 * $role = $rolesTable->get($id, [
 *     'contain' => ['Permissions']
 * ]);
 *
 * // Check role permissions
 * foreach ($role->permissions as $permission) {
 *     echo "Role can: " . $permission->name . "\n";
 * }
 * ```
 *
 * ### Member Role Assignment
 * ```php
 * // Assign role to member with time bounds
 * $memberRole = $memberRolesTable->newEntity([
 *     'Member_id' => $memberId,
 *     'role_id' => $role->id,
 *     'start_on' => new Date('2024-01-01'),
 *     'expires_on' => new Date('2024-12-31'),
 *     'approver_id' => $currentUser->id
 * ]);
 * $memberRolesTable->save($memberRole);
 *
 * // Query members with specific role
 * $members = $role->Members; // Uses lazy loading
 * ```
 *
 * ### Permission Management
 * ```php
 * // Add permissions to role
 * $role = $rolesTable->patchEntity($role, [
 *     'permissions' => [
 *         ['id' => $permissionId1],
 *         ['id' => $permissionId2]
 *     ]
 * ]);
 * $rolesTable->save($role);
 * ```
 *
 * ## Security Considerations
 *
 * ### Mass Assignment Protection
 * - Only specific fields are accessible for mass assignment
 * - Association data requires explicit permission
 * - Prevents unauthorized privilege escalation
 *
 * ### Authorization Integration
 * - Works with RolePolicy for authorization checks
 * - Supports branch-scoped permission inheritance
 * - Integrates with KMP identity system
 *
 * ### Audit Trail
 * - All changes tracked through Footprint behavior
 * - Soft deletion preserves role history
 * - Timestamp tracking for compliance
 *
 * ## Performance Considerations
 *
 * ### Lazy Loading
 * - Uses LazyLoadEntityTrait for efficient association loading
 * - Members and permissions loaded on-demand
 * - Reduces memory usage in list operations
 *
 * ### Caching Integration
 * - Role changes trigger security cache invalidation
 * - Permission lookups benefit from query caching
 * - Association queries optimized for common patterns
 *
 * ## Integration Points
 *
 * ### ActiveWindow System
 * - MemberRole uses ActiveWindowBaseEntity for time-bounded assignments
 * - Automatic expiration and renewal workflows
 * - Integration with warrant and activity systems
 *
 * ### Authorization Service
 * - Role permissions loaded by PermissionsLoader
 * - Cached permission matrix for performance
 * - Branch-scoped permission inheritance
 *
 * ### Organizational Hierarchy
 * - Roles can be branch-specific or global
 * - Permission scoping rules determine access scope
 * - Supports SCA organizational structure
 *
 * @see \App\Model\Table\RolesTable For data access and business logic
 * @see \App\Model\Entity\MemberRole For member-role assignments
 * @see \App\Model\Entity\Permission For permission definitions
 * @see \App\Policy\RolePolicy For authorization rules
 * @see \App\KMP\PermissionsLoader For permission loading logic
 *
 * @property int $id Primary key
 * @property string $name Unique role name (max 255 characters)
 * @property \Cake\I18n\DateTime $created Creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by Creator member ID
 * @property int|null $modified_by Last modifier member ID
 * @property \Cake\I18n\DateTime|null $deleted Soft delete timestamp
 *
 * @property \App\Model\Entity\Member[] $Members Members assigned to this role (through MemberRole)
 * @property \App\Model\Entity\Permission[] $permissions Permissions granted by this role
 * @property \App\Model\Entity\MemberRole[] $MemberRoles Time-bounded role assignments
 * @property \App\Model\Entity\MemberRole[] $CurrentMemberRoles Currently active role assignments
 * @property \App\Model\Entity\MemberRole[] $UpcomingMemberRoles Future role assignments
 * @property \App\Model\Entity\MemberRole[] $PreviousMemberRoles Expired role assignments
 */
class Role extends BaseEntity
{
    use LazyLoadEntityTrait;

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
        'name' => true,
        'Members' => true,
        'permissions' => true,
    ];
}
