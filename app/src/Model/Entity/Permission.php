<?php

declare(strict_types=1);

namespace App\Model\Entity;

use InvalidArgumentException;

/**
 * Permission Entity - KMP RBAC Permission and Access Control System
 *
 * The Permission entity defines specific access rights and capabilities within the KMP system.
 * It serves as the atomic unit of authorization, defining what actions can be performed and
 * under what conditions. Permissions are grouped into roles and can have complex scoping
 * rules that determine their organizational reach and application context.
 *
 * ## Core Permission Architecture
 *
 * ### Permission Types
 * - **System Permissions**: Core administrative functions (user management, system config)
 * - **Super User Permissions**: Unrestricted administrative access
 * - **Activity Permissions**: Linked to specific activities or authorizations
 * - **Organizational Permissions**: Branch-specific or hierarchical access
 *
 * ### Scoping Rules
 * The permission system implements three levels of organizational scope:
 * 
 * #### SCOPE_GLOBAL
 * - **Usage**: System-wide permissions without branch limitations
 * - **Example**: System administration, global reporting, super user functions
 * - **Security**: Highest privilege level, use sparingly
 * 
 * #### SCOPE_BRANCH_ONLY
 * - **Usage**: Permissions limited to a specific branch
 * - **Example**: Local event management, branch-specific reporting
 * - **Security**: Isolated to single organizational unit
 * 
 * #### SCOPE_BRANCH_AND_CHILDREN
 * - **Usage**: Permissions that extend to child branches in hierarchy
 * - **Example**: Regional administration, hierarchical reporting
 * - **Security**: Cascading permissions down organizational tree
 *
 * ## Advanced Permission Features
 *
 * ### Requirement Validation
 * - **Active Membership**: Requires current SCA membership status
 * - **Background Check**: Requires valid background check on file
 * - **Minimum Age**: Age-based permission restrictions
 * - **Warrant Requirement**: Requires active warrant for permission
 *
 * ### Activity Integration
 * - Some permissions are linked to specific activities
 * - Activity-based permissions inherit activity requirements
 * - Supports authorization workflows for activities
 *
 * ### Policy Framework
 * - Permissions can have associated policy classes
 * - Custom authorization logic through PermissionPolicy entities
 * - Dynamic permission evaluation based on context
 *
 * ## Database Schema
 *
 * ### Core Fields
 * - `id` (int): Primary key, auto-increment
 * - `name` (string): Descriptive permission name (max 255 characters)
 * - `scoping_rule` (string): One of the SCOPE_* constants
 * - `is_system` (bool): System-level permission flag
 * - `is_super_user` (bool): Super user permission flag
 *
 * ### Requirement Fields
 * - `require_active_membership` (bool): SCA membership requirement
 * - `require_active_background_check` (bool): Background check requirement
 * - `require_min_age` (int): Minimum age requirement
 * - `requires_warrant` (bool): Active warrant requirement
 *
 * ### Association Fields
 * - `activity_id` (int|null): Optional activity association
 * - Audit fields: created, modified, created_by, modified_by, deleted
 *
 * ## Usage Examples
 *
 * ### Creating Permissions
 * ```php
 * // System permission with global scope
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Manage System Users',
 *     'scoping_rule' => Permission::SCOPE_GLOBAL,
 *     'is_system' => true,
 *     'require_active_membership' => true,
 *     'require_active_background_check' => true,
 *     'require_min_age' => 18
 * ]);
 *
 * // Branch-only permission
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Manage Local Events',
 *     'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
 *     'require_active_membership' => true
 * ]);
 *
 * // Activity-linked permission
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Authorize Archery',
 *     'activity_id' => $archeryActivityId,
 *     'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
 *     'requires_warrant' => true
 * ]);
 * ```
 *
 * ### Permission Checking
 * ```php
 * // Check permission requirements
 * if ($permission->require_active_membership && !$member->hasActiveMembership()) {
 *     throw new UnauthorizedException('Active membership required');
 * }
 *
 * if ($permission->require_min_age && $member->age < $permission->require_min_age) {
 *     throw new UnauthorizedException('Minimum age requirement not met');
 * }
 *
 * // Check scoping rules
 * switch ($permission->scoping_rule) {
 *     case Permission::SCOPE_GLOBAL:
 *         // No branch restrictions
 *         break;
 *     case Permission::SCOPE_BRANCH_ONLY:
 *         // Validate same branch
 *         break;
 *     case Permission::SCOPE_BRANCH_AND_CHILDREN:
 *         // Validate branch hierarchy
 *         break;
 * }
 * ```
 *
 * ### Role Assignment
 * ```php
 * // Add permission to role
 * $role = $rolesTable->patchEntity($role, [
 *     'permissions' => [
 *         ['id' => $permission->id]
 *     ]
 * ]);
 * $rolesTable->save($role);
 *
 * // Query roles with permission
 * $rolesWithPermission = $permission->roles;
 * ```
 *
 * ## Security Considerations
 *
 * ### Scoping Rule Validation
 * - Scoping rules are validated through setter method
 * - Invalid rules throw InvalidArgumentException
 * - Prevents unauthorized scope escalation
 *
 * ### System Permission Protection
 * - System permissions require special handling
 * - Super user permissions need careful assignment
 * - Regular audit of high-privilege permissions
 *
 * ### Requirement Validation
 * - Multiple requirement types can be combined
 * - Age requirements prevent inappropriate access
 * - Background checks for sensitive permissions
 *
 * ## Performance Considerations
 *
 * ### Permission Loading
 * - Permissions cached per user session
 * - Role-permission matrix optimized for lookup
 * - Bulk permission checking for efficiency
 *
 * ### Scoping Performance
 * - Branch hierarchy queries cached
 * - Scope evaluation optimized for common patterns
 * - Database indexes on scoping fields
 *
 * ## Integration Points
 *
 * ### Authorization Service
 * - Permissions loaded by PermissionsLoader class
 * - Integrated with KMP identity interface
 * - Cached permission evaluation results
 *
 * ### Activity System
 * - Activity-linked permissions inherit activity rules
 * - Authorization workflows for activity permissions
 * - Activity expiration affects permission validity
 *
 * ### Branch Hierarchy
 * - Scoping rules leverage branch tree structure
 * - Hierarchical permission inheritance
 * - Branch-based data isolation
 *
 * ### Policy Framework
 * - Custom authorization logic through policies
 * - Dynamic permission evaluation
 * - Context-aware permission checking
 *
 * @see \App\Model\Table\PermissionsTable For data access and validation
 * @see \App\Model\Entity\PermissionPolicy For custom policy associations
 * @see \App\Model\Entity\Role For role-permission assignments
 * @see \App\KMP\PermissionsLoader For permission loading logic
 * @see \App\Services\AuthorizationService For permission checking
 *
 * @property int $id Primary key
 * @property string $name Descriptive permission name (max 255 characters)
 * @property int|null $activity_id Optional associated activity ID
 * @property bool $require_active_membership SCA membership requirement flag
 * @property bool $require_active_background_check Background check requirement flag
 * @property int $require_min_age Minimum age requirement (0 = no requirement)
 * @property bool $is_system System-level permission flag
 * @property bool $is_super_user Super user permission flag
 * @property bool $requires_warrant Active warrant requirement flag
 * @property string $scoping_rule Organizational scope (SCOPE_* constant)
 * @property \Cake\I18n\DateTime $created Creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by Creator member ID
 * @property int|null $modified_by Last modifier member ID
 * @property \Cake\I18n\DateTime|null $deleted Soft delete timestamp
 *
 * @property \App\Model\Entity\Activity|null $activity Associated activity (if activity_id set)
 * @property \App\Model\Entity\Role[] $roles Roles that include this permission
 * @property \App\Model\Entity\PermissionPolicy[] $PermissionPolicies Custom policy associations
 */
class Permission extends BaseEntity
{
    public const SCOPE_GLOBAL = 'Global'; //No Scope limitations
    public const SCOPE_BRANCH_ONLY = 'Branch Only';
    public const SCOPE_BRANCH_AND_CHILDREN = 'Branch and Children'; //Can Login

    //scoping rules as an array for dropdowns
    public const SCOPING_RULES = [
        self::SCOPE_GLOBAL => self::SCOPE_GLOBAL,
        self::SCOPE_BRANCH_ONLY => self::SCOPE_BRANCH_ONLY,
        self::SCOPE_BRANCH_AND_CHILDREN => self::SCOPE_BRANCH_AND_CHILDREN,
    ];

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
        'require_active_membership' => true,
        'require_active_background_check' => true,
        'require_min_age' => true,
        'is_system' => true,
        'is_super_user' => true,
        'requires_warrant' => true,
        'scoping_rule' => true,
        'roles' => true,
    ];

    protected function _setScopeing_rule($value)
    {
        //the status must be one of the constants defined in this class
        switch ($value) {
            case self::SCOPE_GLOBAL:
            case self::SCOPE_BRANCH_ONLY:
            case self::SCOPE_BRANCH_AND_CHILDREN:
                return $value;
            default:
                throw new InvalidArgumentException('Invalid Scoping Rule');
        }
    }
}
