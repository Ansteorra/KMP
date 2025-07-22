<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * PermissionPolicy Entity - Dynamic Permission Authorization Framework
 *
 * The PermissionPolicy entity provides a flexible framework for implementing custom authorization
 * logic beyond standard role-based access control. It allows permissions to have associated policy
 * classes and methods that can perform complex, context-aware authorization decisions based on
 * runtime conditions, entity state, and business rules.
 *
 * ## Policy Framework Architecture
 *
 * ### Dynamic Authorization
 * - Extends basic RBAC with custom authorization logic
 * - Allows runtime evaluation of permission eligibility
 * - Supports complex business rules and conditions
 * - Enables context-aware permission decisions
 *
 * ### Policy Class Integration
 * - References specific policy classes in the Policy namespace
 * - Delegates authorization decisions to policy methods
 * - Supports multiple policies per permission
 * - Follows CakePHP authorization patterns
 *
 * ### Method-Level Granularity
 * - Each policy can specify different methods for different contexts
 * - Allows fine-grained authorization control
 * - Supports action-specific authorization logic
 * - Enables complex permission workflows
 *
 * ## Core Features
 *
 * ### Policy Class Resolution
 * - Policy classes must exist in App\Policy namespace
 * - Class names follow standard CakePHP naming conventions
 * - Supports both entity and table policies
 * - Runtime validation of policy class existence
 *
 * ### Method Invocation
 * - Policy methods receive standard authorization parameters
 * - Methods return boolean authorization decisions
 * - Support for additional context parameters
 * - Integration with CakePHP Authorization plugin
 *
 * ### Permission Enhancement
 * - Adds dynamic behavior to static permissions
 * - Enables conditional permission grants
 * - Supports temporary permission restrictions
 * - Allows permission customization per entity
 *
 * ## Database Schema
 *
 * ### Core Fields
 * - `id` (int): Primary key, auto-increment
 * - `permission_id` (int): Foreign key to permissions table
 * - `policy_class` (string): Full policy class name
 * - `policy_method` (string): Method name within policy class
 *
 * ### Audit Fields
 * - Standard BaseEntity audit trail fields
 * - Creation and modification tracking
 * - Soft deletion support
 *
 * ## Usage Examples
 *
 * ### Creating Policy Associations
 * ```php
 * // Associate permission with custom policy
 * $policy = $permissionPoliciesTable->newEntity([
 *     'permission_id' => $manageMembersPermission->id,
 *     'policy_class' => 'App\\Policy\\MemberPolicy',
 *     'policy_method' => 'canManageInBranch'
 * ]);
 * $permissionPoliciesTable->save($policy);
 *
 * // Activity-specific authorization
 * $policy = $permissionPoliciesTable->newEntity([
 *     'permission_id' => $authorizeArcheryPermission->id,
 *     'policy_class' => 'Activities\\Policy\\AuthorizationPolicy',
 *     'policy_method' => 'canAuthorizeActivity'
 * ]);
 * ```
 *
 * ### Policy Method Implementation
 * ```php
 * // In App\Policy\MemberPolicy
 * public function canManageInBranch(KmpIdentityInterface $user, Member $member): bool
 * {
 *     // Custom logic for member management permissions
 *     if ($user->hasPermission('super_admin')) {
 *         return true;
 *     }
 *
 *     // Check if user can manage members in this branch
 *     $userBranch = $user->getBranchId();
 *     $memberBranch = $member->getBranchId();
 *
 *     // Allow if same branch or user's branch is parent
 *     return $userBranch === $memberBranch || 
 *            $this->branchHierarchy->isParentOf($userBranch, $memberBranch);
 * }
 * ```
 *
 * ### Authorization Service Integration
 * ```php
 * // Permission checking with policy evaluation
 * $hasPermission = $this->Authorization->can($user, 'manage', $member);
 *
 * // The authorization service will:
 * // 1. Check if user has 'manage_members' permission
 * // 2. Load associated permission policies
 * // 3. Execute policy methods for additional validation
 * // 4. Return combined authorization result
 * ```
 *
 * ### Multiple Policies Per Permission
 * ```php
 * // Permission can have multiple policy validations
 * $policies = [
 *     [
 *         'permission_id' => $editEventPermission->id,
 *         'policy_class' => 'App\\Policy\\EventPolicy',
 *         'policy_method' => 'canEditEvent'
 *     ],
 *     [
 *         'permission_id' => $editEventPermission->id,
 *         'policy_class' => 'App\\Policy\\BranchPolicy',
 *         'policy_method' => 'isWithinScope'
 *     ]
 * ];
 * 
 * // All policies must pass for permission to be granted
 * ```
 *
 * ## Security Considerations
 *
 * ### Policy Class Validation
 * - Policy classes must exist and be accessible
 * - Method names validated at runtime
 * - Prevents code injection through policy references
 * - Follows namespace security patterns
 *
 * ### Authorization Logic Security
 * - Policy methods should be thoroughly tested
 * - Avoid complex logic that's hard to audit
 * - Document policy behavior clearly
 * - Regular review of policy implementations
 *
 * ### Performance Impact
 * - Policy evaluation adds runtime overhead
 * - Cache policy results where appropriate
 * - Optimize frequently-called policy methods
 * - Monitor policy execution times
 *
 * ## Performance Considerations
 *
 * ### Policy Caching
 * - Policy associations loaded with permissions
 * - Policy results can be cached per request
 * - Bulk policy evaluation for efficiency
 * - Database indexes on permission_id
 *
 * ### Execution Optimization
 * - Policy methods should be lightweight
 * - Avoid expensive operations in policies
 * - Use eager loading for policy dependencies
 * - Profile policy execution performance
 *
 * ## Integration Points
 *
 * ### CakePHP Authorization Plugin
 * - Integrates with standard authorization flow
 * - Extends policy resolver functionality
 * - Supports authorization middleware
 * - Compatible with existing policy patterns
 *
 * ### Permission System
 * - Enhances basic permission checking
 * - Provides conditional permission grants
 * - Supports complex authorization workflows
 * - Maintains audit trail of policy decisions
 *
 * ### Business Logic Layer
 * - Policies can access service layer
 * - Integration with business rule engine
 * - Support for complex approval workflows
 * - Dynamic permission calculation
 *
 * ## Best Practices
 *
 * ### Policy Design
 * - Keep policy methods focused and simple
 * - Document policy behavior clearly
 * - Use descriptive method names
 * - Follow single responsibility principle
 *
 * ### Testing
 * - Unit test all policy methods
 * - Test edge cases and error conditions
 * - Integration tests for policy chains
 * - Performance testing for complex policies
 *
 * ### Maintenance
 * - Regular audit of policy associations
 * - Monitor policy performance metrics
 * - Update policies when business rules change
 * - Document policy dependencies
 *
 * @see \App\Model\Table\PermissionPoliciesTable For data access and validation
 * @see \App\Model\Entity\Permission For permission definitions
 * @see \App\Policy\BasePolicy For policy base class
 * @see \App\Services\AuthorizationService For policy execution
 *
 * @property int $id Primary key
 * @property int $permission_id Foreign key to permissions table
 * @property string $policy_class Full policy class name (e.g., 'App\\Policy\\MemberPolicy')
 * @property string $policy_method Method name within policy class
 * @property \Cake\I18n\DateTime $created Creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by Creator member ID
 * @property int|null $modified_by Last modifier member ID
 * @property \Cake\I18n\DateTime|null $deleted Soft delete timestamp
 *
 * @property \App\Model\Entity\Permission $permission Associated permission entity
 */
class PermissionPolicy extends BaseEntity
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
        'permission_id' => true,
        'policy_class' => true,
        'policy_method' => true,
        'permission' => true,
    ];
}
