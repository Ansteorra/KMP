<?php

declare(strict_types=1);

namespace App\KMP;

use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Model\Entity\Warrant;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * PermissionsLoader - KMP RBAC Security Engine and Permission Validation System
 *
 * The PermissionsLoader is the core security engine of the KMP system, responsible for
 * validating all permission requests and enforcing Role-Based Access Control (RBAC)
 * with warrant temporal validation. This class serves as the authoritative source for
 * permission evaluation, combining member roles, permissions, warrants, and policy
 * frameworks into a comprehensive security architecture.
 *
 * ## Core Security Architecture
 *
 * ### RBAC Validation Chain
 * The permission validation process follows a multi-layered security model:
 * 
 * 1. **Member Identity Validation**
 *    - Verify member exists and is authenticated
 *    - Check member status (verified membership, minor, etc.)
 *    - Validate active membership expiration dates
 * 
 * 2. **Role Assignment Validation**
 *    - Verify active role assignments (start_on/expires_on dates)
 *    - Check role-permission associations
 *    - Validate branch-based role scoping
 * 
 * 3. **Permission Requirement Validation**
 *    - Active Membership: Requires current SCA membership status
 *    - Background Check: Requires valid background check on file
 *    - Minimum Age: Age-based permission restrictions with birth year/month validation
 *    - Warrant Requirement: Requires active warrant for permission
 * 
 * 4. **Warrant Temporal Validation** (when enabled)
 *    - Verify warrant status (CURRENT_STATUS only)
 *    - Check warrant temporal boundaries (start_on < now < expires_on)
 *    - Validate warrant-role relationship through member_role_id
 *    - Configurable via 'KMP.RequireActiveWarrantForSecurity' setting
 * 
 * 5. **Policy Framework Integration**
 *    - Dynamic policy class resolution and method invocation
 *    - Method-level authorization granularity
 *    - Integration with CakePHP authorization plugin
 * 
 * 6. **Branch Scoping Enforcement**
 *    - Global: System-wide access without branch limitations
 *    - Branch Only: Access limited to specific branch
 *    - Branch and Children: Hierarchical access with descendant inclusion
 *
 * ### Performance Optimization Strategy
 *
 * #### Multi-Tier Caching System
 * - **Member Permissions Cache**: `member_permissions{memberId}` - Complete permission set per member
 * - **Permission Policies Cache**: `permissions_policies{memberId}` - Policy mappings per member
 * - **Permission Members Cache**: `permissions_members{permissionId}` - Members with specific permission
 * - **Cache Configuration**: Uses 'member_permissions' and 'permissions' cache configs
 * 
 * #### Query Optimization
 * - Complex SQL generation with optimized JOIN strategies
 * - Subquery optimization for warrant validation
 * - Distinct result sets to prevent permission duplication
 * - Branch hierarchy caching for descendant/parent lookups
 *
 * ## Integration Points
 *
 * ### Member Identity System
 * - Integrates with Member entity for identity verification
 * - Uses Member::STATUS_* constants for status validation
 * - Validates membership expiration and background check dates
 * 
 * ### Authorization Service
 * - Primary data source for AuthorizationService
 * - Provides permission checking and state management
 * - Supports controller and service-level authorization
 * 
 * ### Policy Framework
 * - Discovers policy classes from app/src/Policy and plugin Policy directories
 * - Validates policy methods (must start with 'can' and be public)
 * - Supports dynamic method generation via getDynamicMethods()
 * 
 * ### Warrant System
 * - Temporal validation layer for RBAC permissions
 * - Configurable warrant requirement enforcement
 * - Integration with member warrantable status
 *
 * ## Security Considerations
 *
 * ### Critical Security Features
 * - All permission checks validated through central engine
 * - Temporal validation prevents expired permission usage
 * - Branch scoping prevents unauthorized cross-organizational access
 * - Warrant validation adds additional security layer for sensitive permissions
 * - Cache invalidation prevents stale permission data
 * 
 * ### Performance vs Security Balance
 * - Aggressive caching with proper invalidation strategies
 * - Complex validation chains optimized for security over speed
 * - Database-level validation with application-level enforcement
 * - Multi-layer validation prevents permission escalation attacks
 *
 * ## Usage Examples
 *
 * ### Basic Permission Loading
 * ```php
 * // Load all permissions for a member
 * $permissions = PermissionsLoader::getPermissions($memberId);
 * 
 * // Check for specific permission
 * if (isset($permissions[$permissionId])) {
 *     $permission = $permissions[$permissionId];
 *     // Check branch scope
 *     if ($permission->scoping_rule === Permission::SCOPE_GLOBAL || 
 *         in_array($currentBranchId, $permission->branch_ids)) {
 *         // Permission granted
 *     }
 * }
 * ```
 * 
 * ### Policy Framework Integration
 * ```php
 * // Get policy methods for member
 * $policies = PermissionsLoader::getPolicies($memberId, [$branchId]);
 * 
 * // Check specific policy method
 * if (isset($policies['App\Policy\MemberPolicy']['canEdit'])) {
 *     $policy = $policies['App\Policy\MemberPolicy']['canEdit'];
 *     // Use policy for authorization
 * }
 * ```
 * 
 * ### Member Query Generation
 * ```php
 * // Get members with specific permission in branch
 * $query = PermissionsLoader::getMembersWithPermissionsQuery($permissionId, $branchId);
 * $members = $query->all();
 * ```
 * 
 * ### Application Policy Discovery
 * ```php
 * // Discover all available policy classes and methods
 * $policies = PermissionsLoader::getApplicationPolicies();
 * foreach ($policies as $class => $methods) {
 *     foreach ($methods as $method) {
 *         // Register policy method
 *     }
 * }
 * ```
 *
 * @package App\KMP
 * @see \App\Model\Entity\Permission Permission entity for permission structure
 * @see \App\Model\Entity\Member Member entity for identity requirements
 * @see \App\Model\Entity\Warrant Warrant entity for temporal validation
 * @see \App\Services\AuthorizationService Authorization service integration
 * @see \App\Policy\BasePolicy Base policy class for custom policies
 */
class PermissionsLoader
{
    /**
     * Get Complete Permissions Set for Member - Core RBAC Permission Loading
     *
     * This is the primary method for loading all permissions available to a member,
     * combining role assignments, permission scoping rules, warrant validation,
     * and policy framework integration. The method performs comprehensive validation
     * of the entire permission chain and returns a structured permission set.
     *
     * ## Permission Loading Process
     *
     * ### 1. Cache Strategy
     * - **Cache Key**: `member_permissions{memberId}`
     * - **Cache Config**: 'member_permissions'
     * - **Performance**: Eliminates expensive validation chain for repeated requests
     * - **Invalidation**: Triggered by role changes, warrant updates, member modifications
     *
     * ### 2. Complex Query Construction
     * The method builds a sophisticated query that validates:
     * - Active role assignments with temporal boundaries
     * - Permission requirements (membership, background check, age, warrants)
     * - Branch-based permission scoping
     * - Policy framework associations
     *
     * ### 3. Permission Merging Logic
     * When a member has the same permission through multiple roles/branches:
     * - **SCOPE_GLOBAL**: No branch restrictions (highest privilege)
     * - **SCOPE_BRANCH_ONLY**: Specific branch IDs collected
     * - **SCOPE_BRANCH_AND_CHILDREN**: Branch descendants calculated and merged
     *
     * ### 4. Policy Integration
     * Permissions with associated PermissionPolicies are enhanced with:
     * - Policy class resolution for dynamic authorization
     * - Method-level permission granularity
     * - Integration with CakePHP authorization plugin
     *
     * ## Return Structure
     *
     * Returns an associative array indexed by permission ID, with each permission
     * containing:
     *
     * ```php
     * [
     *     $permissionId => (object)[
     *         'id' => int,                    // Permission ID
     *         'name' => string,               // Permission name
     *         'scoping_rule' => string,       // SCOPE_GLOBAL|SCOPE_BRANCH_ONLY|SCOPE_BRANCH_AND_CHILDREN
     *         'is_super_user' => bool,        // Super user permission flag
     *         'branch_ids' => array|null,     // Allowed branch IDs (null for global)
     *         'entity_id' => int|null,        // Associated entity ID
     *         'entity_type' => string|null,   // Associated entity type
     *         'policies' => [                 // Policy framework integration
     *             'PolicyClass' => [
     *                 'methodName' => int     // Policy ID
     *             ]
     *         ]
     *     ]
     * ]
     * ```
     *
     * ## Security Features
     *
     * ### Temporal Validation
     * - Role assignments must be currently active (start_on < now, expires_on > now or null)
     * - Membership expiration validation for relevant permissions
     * - Background check expiration validation when required
     * - Warrant temporal boundaries when warrant requirement enabled
     *
     * ### Branch Scoping Security
     * - Global permissions bypass branch restrictions (use carefully)
     * - Branch-only permissions isolated to specific organizational units
     * - Hierarchical permissions include descendant branches automatically
     * - Branch descendant calculation cached for performance
     *
     * ### Age-Based Restrictions
     * - Complex age calculation using birth year and month
     * - Handles edge cases for birthday validation within current year
     * - Prevents underage access to restricted permissions
     *
     * ## Performance Considerations
     *
     * ### Query Optimization
     * - Uses innerJoinWith for efficient role-permission association
     * - Distinct results prevent permission duplication
     * - Complex WHERE clauses optimized for database indexes
     * - Branch hierarchy queries cached for repeated access
     *
     * ### Memory Management
     * - Lazy loading of permission policies
     * - Efficient array merging for branch ID collections
     * - Cache storage optimized for serialization
     *
     * ## Integration Examples
     *
     * ### Controller Authorization
     * ```php
     * public function beforeFilter(EventInterface $event)
     * {
     *     $permissions = PermissionsLoader::getPermissions($this->getRequest()->getAttribute('identity')->id);
     *     $this->set('userPermissions', $permissions);
     * }
     * ```
     *
     * ### Service Layer Permission Checking
     * ```php
     * public function canPerformAction($memberId, $permissionName, $branchId = null)
     * {
     *     $permissions = PermissionsLoader::getPermissions($memberId);
     *     foreach ($permissions as $permission) {
     *         if ($permission->name === $permissionName) {
     *             if ($permission->scoping_rule === Permission::SCOPE_GLOBAL) {
     *                 return true;
     *             }
     *             if ($branchId && in_array($branchId, $permission->branch_ids)) {
     *                 return true;
     *             }
     *         }
     *     }
     *     return false;
     * }
     * ```
     *
     * ### Branch Descendant Resolution
     * ```php
     * $permissions = PermissionsLoader::getPermissions($memberId);
     * foreach ($permissions as $permission) {
     *     if ($permission->scoping_rule === Permission::SCOPE_BRANCH_AND_CHILDREN) {
     *         // $permission->branch_ids includes all descendant branches
     *         $allowedBranches = $permission->branch_ids;
     *     }
     * }
     * ```
     *
     * @param int $memberId The member ID to load permissions for
     * @return array Associative array of permission objects indexed by permission ID
     * @throws \Exception When member validation fails or database errors occur
     * @see self::validPermissionClauses() For detailed validation logic
     * @see \App\Model\Entity\Permission For permission scoping constants
     * @see \App\Model\Table\BranchesTable::getAllDecendentIds() For branch hierarchy
     */
    public static function getPermissions(int $memberId): array
    {
        // 1. Cache Strategy - Check for cached permissions first
        $cacheKey = 'member_permissions' . $memberId;
        $cache = Cache::read($cacheKey, 'member_permissions');
        if ($cache) {
            return $cache; // Return cached result if available
        }

        // 2. Initialize Table Locators - Get required table instances
        $branchTable = TableRegistry::getTableLocator()->get('Branches');
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');

        // 3. Build Complex Permission Query
        $query = $permissionsTable->find();
        $query = self::validPermissionClauses($query) // Apply comprehensive validation chain
            ->select([
                'Permissions.id',
                'Permissions.name',
                'Permissions.scoping_rule',
                'Permissions.is_super_user',
                'MemberRoles.branch_id',    // Branch context for permission
                'MemberRoles.entity_id',    // Associated entity (if any)
                'MemberRoles.entity_type',  // Associated entity type (if any)
            ])
            ->contain(['PermissionPolicies']) // Include policy framework data
            ->where(['Members.id' => $memberId]) // Filter for specific member
            ->distinct() // Prevent duplicate permissions
            ->all()
            ->toArray();

        // 4. Permission Merging and Scoping Logic
        $permissions = [];

        foreach ($query as $permission) {
            // Extract role assignment context from matching data
            $branch_id = $permission->_matchingData['MemberRoles']->branch_id;
            $entity_id = $permission->_matchingData['MemberRoles']->entity_id;
            $entity_type = $permission->_matchingData['MemberRoles']->entity_type;

            // Check if permission already exists (from multiple role assignments)
            if (isset($permissions[$permission->id])) {
                // Merge branch access based on scoping rule
                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        // Global permissions have no branch restrictions
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        // Add specific branch to allowed list
                        $permissions[$permission->id]->branch_ids[] = $branch_id;
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        // Include branch and all descendants
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id; // Include the branch itself
                        $idList = array_merge(
                            $permissions[$permission->id]->branch_ids,
                            $decendents,
                        );
                        $idList = array_unique($idList); // Remove duplicates
                        $permissions[$permission->id]->branch_ids = $idList;
                        break;
                }
            } else {
                // Create new permission object
                $permissions[$permission->id] = (object)[
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'scoping_rule' => $permission->scoping_rule,
                    'is_super_user' => $permission->is_super_user,
                    'branch_ids' => [],
                    'entity_id' => $entity_id,
                    'entity_type' =>  $entity_type,
                ];

                // 5. Policy Framework Integration
                if ($permission->permission_policies) {
                    foreach ($permission->permission_policies as $policy) {
                        // Build policy class -> method -> policy ID mapping
                        $permissions[$permission->id]->policies[$policy->policy_class][$policy->policy_method] = $policy->id;
                    }
                }

                // Set initial branch scope based on scoping rule
                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        // Global permissions have no branch restrictions
                        $permissions[$permission->id]->branch_ids = null;
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        // Limit to specific branch
                        $permissions[$permission->id]->branch_ids = [$branch_id];
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        // Include branch and all descendants
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id; // Include the branch itself
                        $permissions[$permission->id]->branch_ids = $decendents;
                        break;
                }
            }
        }

        // 6. Cache Result for Performance
        Cache::write($cacheKey, $permissions, 'member_permissions');

        return $permissions;
    }

    /**
     * Get Policy Framework Mappings for Member - Dynamic Authorization Integration
     *
     * This method extracts policy class and method mappings from a member's permission set,
     * providing the foundation for dynamic authorization through the CakePHP policy framework.
     * It processes permissions obtained from getPermissions() and creates policy-specific
     * authorization mappings with branch scoping support.
     *
     * ## Policy Framework Integration
     *
     * ### Policy Structure
     * The method returns a nested structure organizing policies by:
     * - **Policy Class**: Full namespace class name (e.g., 'App\Policy\MemberPolicy')
     * - **Policy Method**: Method name within the policy class (e.g., 'canEdit')
     * - **Authorization Data**: Scoping rules and branch restrictions
     *
     * ### Branch Scoping Logic
     * When multiple permissions grant access to the same policy method:
     * - **Global Permissions**: Override all branch restrictions (highest privilege)
     * - **Branch Merging**: Combines branch IDs from multiple role assignments
     * - **Branch Filtering**: Optional branchIds parameter restricts scope
     * - **Empty Branch Cleanup**: Removes policies with no valid branches
     *
     * ### Caching Strategy
     * - **Cache Key**: `permissions_policies{memberId}`
     * - **Cache Config**: 'member_permissions'
     * - **Dependency**: Inherits cache invalidation from getPermissions()
     *
     * ## Return Structure
     *
     * Returns a nested associative array organized by policy class and method:
     *
     * ```php
     * [
     *     'App\Policy\MemberPolicy' => [
     *         'canView' => (object)[
     *             'scoping_rule' => string,     // Permission scoping rule
     *             'branch_ids' => array|null,   // Allowed branch IDs
     *             'entity_id' => int|null,      // Associated entity ID
     *             'entity_type' => string|null  // Associated entity type
     *         ],
     *         'canEdit' => (object)[...],
     *     ],
     *     'App\Policy\BranchPolicy' => [
     *         'canManage' => (object)[...],
     *     ]
     * ]
     * ```
     *
     * ## Authorization Integration Examples
     *
     * ### Controller Policy Resolution
     * ```php
     * $policies = PermissionsLoader::getPolicies($memberId, [$currentBranchId]);
     * if (isset($policies['App\Policy\MemberPolicy']['canEdit'])) {
     *     $policy = $policies['App\Policy\MemberPolicy']['canEdit'];
     *     // Use with CakePHP Authorization plugin
     *     $this->Authorization->authorize($entity, 'edit');
     * }
     * ```
     *
     * ### Service Layer Authorization
     * ```php
     * public function canPerformAction($memberId, $policyClass, $method, $branchId = null)
     * {
     *     $policies = PermissionsLoader::getPolicies($memberId);
     *     if (!isset($policies[$policyClass][$method])) {
     *         return false;
     *     }
     *     
     *     $policy = $policies[$policyClass][$method];
     *     if ($policy->scoping_rule === Permission::SCOPE_GLOBAL) {
     *         return true;
     *     }
     *     
     *     return $branchId && in_array($branchId, $policy->branch_ids);
     * }
     * ```
     *
     * ### Branch-Scoped Policy Checking
     * ```php
     * // Get policies limited to specific branches
     * $branchIds = [1, 2, 3]; // Current user's accessible branches
     * $policies = PermissionsLoader::getPolicies($memberId, $branchIds);
     * 
     * // Check if user can edit members in any of their branches
     * if (isset($policies['App\Policy\MemberPolicy']['canEdit'])) {
     *     $allowedBranches = $policies['App\Policy\MemberPolicy']['canEdit']->branch_ids;
     *     // Use $allowedBranches for authorization logic
     * }
     * ```
     *
     * @param int $id Member ID to get policies for
     * @param array|null $branchIds Optional array of branch IDs to filter policies by
     * @return array Nested array of policy classes, methods, and authorization data
     * @see self::getPermissions() Primary permission loading method
     * @see \App\Policy\BasePolicy Base policy class for implementation patterns
     * @see \App\Model\Entity\Permission For scoping rule constants
     */
    public static function getPolicies($id, ?array $branchIds = null)
    {
        // 1. Cache Strategy - Check for cached policy mappings
        $cacheKey = 'permissions_policies' . $id;
        $cache = Cache::read($cacheKey, 'member_permissions');
        if ($cache) {
            return $cache; // Return cached result if available
        }

        // 2. Load Base Permissions - Get complete permission set
        $permissions = self::getPermissions($id);
        $policies = [];

        // 3. Extract Policy Mappings from Permissions
        foreach ($permissions as $permission) {
            if (isset($permission->policies)) {
                foreach ($permission->policies as $policyClass => $methods) {
                    // Initialize policy class if not exists
                    if (!isset($policies[$policyClass])) {
                        $policies[$policyClass] = [];
                    }

                    // Process each policy method
                    foreach ($methods as $method => $policyId) {
                        if (!isset($policies[$policyClass][$method])) {
                            // Create new policy method entry
                            $policies[$policyClass][$method] = (object)[
                                'scoping_rule' => $permission->scoping_rule,
                                'branch_ids' => $permission->branch_ids,
                                'entity_id' => $permission->entity_id,
                                'entity_type' => $permission->entity_type,
                            ];
                        } else {
                            // Merge multiple permissions for same policy method
                            if ($permission->scoping_rule == Permission::SCOPE_GLOBAL) {
                                // Global permissions override all branch restrictions
                                $policies[$policyClass][$method]->branch_ids = null;
                                $policies[$policyClass][$method]->scoping_rule = Permission::SCOPE_GLOBAL;
                            } elseif ($policies[$policyClass][$method]->scoping_rule != Permission::SCOPE_GLOBAL) {
                                // Merge branch IDs for non-global permissions
                                $policies[$policyClass][$method]->branch_ids = array_merge(
                                    $policies[$policyClass][$method]->branch_ids,
                                    $permission->branch_ids
                                );
                            }
                        }
                    }
                }
            }
        }

        // 4. Branch ID Cleanup and Optimization
        foreach ($policies as $policyClass => $methods) {
            foreach ($methods as $method => $policy) {
                if ($policy->branch_ids) {
                    // Remove duplicate branch IDs for performance
                    $policy->branch_ids = array_unique($policy->branch_ids);
                }
            }
        }

        // 5. Optional Branch Filtering
        if ($branchIds) {
            foreach ($policies as $policyClass => $methods) {
                foreach ($methods as $method => $policy) {
                    if ($policy->branch_ids) {
                        // Restrict to only specified branch IDs
                        $policy->branch_ids = array_intersect($policy->branch_ids, $branchIds);
                    }
                }
            }
        }

        // 6. Remove Empty Policies - Clean up policies with no valid branches
        foreach ($policies as $policyClass => $methods) {
            foreach ($methods as $method => $policy) {
                if (empty($policy->branch_ids) && $policy->scoping_rule != Permission::SCOPE_GLOBAL) {
                    // Remove policies with no branch access (except global)
                    unset($policies[$policyClass][$method]);
                }
            }
        }

        // 7. Remove Empty Policy Classes
        foreach ($policies as $policyClass => $methods) {
            if (empty($methods)) {
                unset($policies[$policyClass]);
            }
        }

        // 8. Cache Result for Performance
        Cache::write($cacheKey, $policies, 'member_permissions');

        return $policies;
    }

    /**
     * Get Members with Specific Permission Query - Reverse Permission Lookup
     *
     * This method generates a query to find all members who have a specific permission
     * within a given branch context. It's the reverse operation of getPermissions(),
     * useful for administrative interfaces, reporting, and authorization management.
     * The method respects permission scoping rules and applies the same validation
     * chain used in permission checking.
     *
     * ## Query Construction Process
     *
     * ### 1. Permission Scope Analysis
     * The method first loads the target permission to determine its scoping rule:
     * - **SCOPE_GLOBAL**: All members with permission (no branch filtering)
     * - **SCOPE_BRANCH_ONLY**: Members with permission in exact branch match
     * - **SCOPE_BRANCH_AND_CHILDREN**: Members with permission in branch or parent hierarchy
     *
     * ### 2. Validation Chain Application
     * Uses validPermissionClauses() to apply the same security validation as permission checking:
     * - Active role assignments with temporal boundaries
     * - Member status validation (verified membership, minor status)
     * - Background check requirements
     * - Age-based restrictions
     * - Warrant requirements (when enabled)
     *
     * ### 3. Branch Hierarchy Resolution
     * For hierarchical permissions (SCOPE_BRANCH_AND_CHILDREN):
     * - Calculates all parent branches of the target branch
     * - Includes members who have the permission at any parent level
     * - Allows hierarchical administrative control patterns
     *
     * ### 4. Performance Optimization
     * - **Caching**: Results cached with `permissions_members{permissionId}` key
     * - **Subquery**: Efficient member ID collection before main query
     * - **Distinct Results**: Prevents duplicate members from multiple role paths
     *
     * ## Branch Scoping Logic
     *
     * ### SCOPE_GLOBAL Permissions
     * ```sql
     * -- No branch filtering - all members with permission
     * SELECT Members.* FROM members 
     * WHERE Members.id IN (
     *     SELECT DISTINCT Members.id FROM permissions_subquery
     * )
     * ```
     *
     * ### SCOPE_BRANCH_ONLY Permissions
     * ```sql
     * -- Exact branch match only
     * WHERE MemberRoles.branch_id = $branch_id
     * ```
     *
     * ### SCOPE_BRANCH_AND_CHILDREN Permissions
     * ```sql
     * -- Current branch and all parent branches
     * WHERE MemberRoles.branch_id IN (parent_branch_ids + current_branch_id)
     * ```
     *
     * ## Integration Examples
     *
     * ### Administrative Member Listing
     * ```php
     * // Get all members who can manage warrants in a branch
     * $permissionId = 15; // "manage_warrants" permission
     * $branchId = 5;      // Current branch
     * 
     * $query = PermissionsLoader::getMembersWithPermissionsQuery($permissionId, $branchId);
     * $authorizedMembers = $query->contain(['Branches'])->all();
     * 
     * foreach ($authorizedMembers as $member) {
     *     echo "Member {$member->name} can manage warrants\n";
     * }
     * ```
     *
     * ### Permission Reporting
     * ```php
     * // Generate report of members with specific permission
     * $query = PermissionsLoader::getMembersWithPermissionsQuery($permissionId, $branchId);
     * $count = $query->count();
     * 
     * $report = [
     *     'permission_name' => $permission->name,
     *     'branch_name' => $branch->name,
     *     'authorized_members_count' => $count,
     *     'members' => $query->select(['id', 'name', 'email'])->all()
     * ];
     * ```
     *
     * ### Notification Target Selection
     * ```php
     * // Find all members who should receive admin notifications
     * $adminPermissionId = 1; // "system_admin" permission
     * $globalBranchId = 1;    // Root organization branch
     * 
     * $query = PermissionsLoader::getMembersWithPermissionsQuery($adminPermissionId, $globalBranchId);
     * $admins = $query->select(['email'])->all();
     * 
     * foreach ($admins as $admin) {
     *     $this->sendNotification($admin->email, $message);
     * }
     * ```
     *
     * ### Hierarchical Authorization Checking
     * ```php
     * // Check if any parent branch administrators can authorize action
     * $query = PermissionsLoader::getMembersWithPermissionsQuery($authPermissionId, $currentBranchId);
     * $hasAuthorizer = $query->count() > 0;
     * 
     * if (!$hasAuthorizer) {
     *     throw new UnauthorizedException('No authorized members found for approval');
     * }
     * ```
     *
     * ## Performance Considerations
     *
     * ### Query Optimization
     * - Subquery pattern minimizes JOIN complexity
     * - Cached permission data reduces repeated validation overhead
     * - Efficient branch hierarchy lookups using tree structure
     * - Database indexes on member_roles.branch_id and permissions.id
     *
     * ### Memory Management
     * - Returns Query object (not executed results) for lazy evaluation
     * - Allows further query modification before execution
     * - Supports pagination and additional filtering by caller
     *
     * @param int $permissionId The permission ID to search for
     * @param int $branch_id The branch context for scoped permission checking
     * @return SelectQuery Query object ready for execution or further modification
     * @throws \Exception When permission not found or database errors occur
     * @see self::validPermissionClauses() For validation chain details
     * @see \App\Model\Table\BranchesTable::getAllParents() For hierarchy resolution
     * @see \App\Model\Entity\Permission For scoping rule constants
     */
    public static function getMembersWithPermissionsQuery(int $permissionId, int $branch_id): SelectQuery
    {
        // 1. Initialize Required Tables
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $memberTable = TableRegistry::getTableLocator()->get('Members');
        $branchTable = TableRegistry::getTableLocator()->get('Branches');

        // 2. Load Permission for Scoping Rule Analysis
        $permission = $permissionsTable->get($permissionId);

        // 3. Build Subquery with Validation Chain
        $subquery = $permissionsTable
            ->find()
            ->cache('permissions_members' . $permissionId, 'permissions'); // Cache for performance

        // Apply comprehensive validation chain (same as getPermissions)
        $subquery = self::validPermissionClauses($subquery)
            ->where(['Permissions.id' => $permissionId]) // Filter for specific permission
            ->select(['Members.id']) // Only need member IDs for subquery
            ->distinct(); // Prevent duplicate members

        // 4. Apply Branch Scoping Logic
        if ($permission->scoping_rule == Permission::SCOPE_BRANCH_ONLY) {
            // Exact branch match only - most restrictive
            $subquery = $subquery->where(['MemberRoles.branch_id' => $branch_id]);
        }

        if ($permission->scoping_rule == Permission::SCOPE_BRANCH_AND_CHILDREN) {
            // Include current branch and all parent branches in hierarchy
            $parents = $branchTable->getAllParents($branch_id);
            $parents[] = $branch_id; // Include the branch itself
            $subquery = $subquery->where(['MemberRoles.branch_id IN ' => $parents]);
        }

        // Note: SCOPE_GLOBAL permissions have no branch filtering applied

        // 5. Build Final Member Query
        $query = $memberTable->find()
            ->where(['Members.id IN' => $subquery]); // Use subquery for member selection

        return $query; // Return query object for further modification by caller
    }

    /**
     * Apply Comprehensive Permission Validation Chain - Core RBAC Security Logic
     *
     * This is the heart of the KMP permission validation system, implementing a multi-layered
     * security validation chain that ensures only properly authorized members receive permissions.
     * This method is used by all permission checking operations to apply consistent validation
     * rules across the entire system.
     *
     * ## Validation Chain Components
     *
     * ### 1. Role Assignment Temporal Validation
     * Ensures role assignments are currently active:
     * - **start_on < now**: Role assignment has started
     * - **expires_on > now OR NULL**: Role assignment hasn't expired (NULL = permanent)
     * - Prevents use of expired or future role assignments
     *
     * ### 2. Member Status and Membership Validation
     * When permission requires active membership:
     * - **Member Status**: Must be VERIFIED_MEMBERSHIP or VERIFIED_MINOR
     * - **Membership Expiration**: membership_expires_on > now
     * - **Conditional**: Only applied when permission.require_active_membership = true
     * - **Business Rule**: Protects sensitive operations requiring valid SCA membership
     *
     * ### 3. Background Check Validation
     * When permission requires background check:
     * - **Expiration Check**: background_check_expires_on > now
     * - **Conditional**: Only applied when permission.require_active_background_check = true
     * - **Security**: Ensures members handling sensitive data have current background checks
     *
     * ### 4. Age-Based Access Control
     * Complex age validation using birth year and month:
     * - **Primary Check**: birth_year < (current_year - required_age)
     * - **Birthday Edge Case**: birth_year = (current_year - required_age) AND birth_month <= current_month
     * - **Conditional**: Only applied when permission.require_min_age > 0
     * - **Use Cases**: Prevents minors from accessing adult-only functions
     *
     * ### 5. Warrant Temporal Validation Layer (Configurable)
     * Advanced security layer for warrant-protected permissions:
     * - **Configuration**: Controlled by 'KMP.RequireActiveWarrantForSecurity' setting
     * - **Warrant Status**: Must be Warrant::CURRENT_STATUS
     * - **Temporal Boundaries**: start_on < now < expires_on
     * - **Member Eligibility**: Member must have warrantable = true
     * - **Role Linkage**: Warrant must be linked to specific member role (member_role_id)
     * - **Conditional**: Only applied when permission.requires_warrant = true
     *
     * ## Technical Implementation Details
     *
     * ### Query Structure
     * ```sql
     * SELECT ... FROM permissions
     * INNER JOIN roles ON permissions.id = role_permissions.permission_id
     * INNER JOIN member_roles ON roles.id = member_roles.role_id
     * INNER JOIN members ON member_roles.member_id = members.id
     * WHERE 
     *   -- Role temporal validation
     *   member_roles.start_on < NOW() AND 
     *   (member_roles.expires_on IS NULL OR member_roles.expires_on > NOW())
     *   
     *   -- Membership validation (conditional)
     *   AND (permissions.require_active_membership = false OR (
     *     members.status IN ('verified_membership', 'verified_minor') AND
     *     members.membership_expires_on > NOW()
     *   ))
     *   
     *   -- Background check validation (conditional)  
     *   AND (permissions.require_active_background_check = false OR
     *     members.background_check_expires_on > NOW())
     *   
     *   -- Age validation (conditional)
     *   AND (permissions.require_min_age = 0 OR (
     *     members.birth_year < (YEAR(NOW()) - permissions.require_min_age) OR
     *     (members.birth_year = (YEAR(NOW()) - permissions.require_min_age) AND 
     *      members.birth_month <= MONTH(NOW()))
     *   ))
     *   
     *   -- Warrant validation (conditional)
     *   AND (permissions.requires_warrant = false OR (
     *     members.warrantable = true AND
     *     member_roles.id IN (
     *       SELECT member_role_id FROM warrants 
     *       WHERE start_on < NOW() AND expires_on > NOW() 
     *       AND status = 'current'
     *     )
     *   ))
     * ```
     *
     * ### Performance Optimizations
     * - **JOIN Strategy**: Uses innerJoinWith for efficient role-member association
     * - **Warrant Subquery**: Optimized subquery for warrant validation to minimize JOIN overhead
     * - **Conditional Logic**: Validation only applied when permission requires it
     * - **Index Usage**: Optimized for database indexes on temporal and status fields
     *
     * ## Security Architecture
     *
     * ### Defense in Depth
     * - **Multiple Validation Layers**: Each adds security without single points of failure
     * - **Temporal Security**: Time-based validation prevents expired credential usage
     * - **Status Validation**: Member status prevents unauthorized access
     * - **Configurable Warrant Layer**: Additional security for sensitive operations
     *
     * ### Attack Prevention
     * - **Permission Escalation**: Multiple validation layers prevent privilege escalation
     * - **Expired Credential Usage**: Temporal validation blocks expired access
     * - **Age-Based Attacks**: Birth date validation prevents underage access
     * - **Background Check Bypass**: Prevents access without valid background checks
     *
     * ## Configuration Integration
     *
     * ### Warrant Requirement Toggle
     * ```php
     * // In app configuration
     * 'KMP' => [
     *     'RequireActiveWarrantForSecurity' => 'yes' // Enable warrant validation
     * ]
     * ```
     *
     * ### Permission Requirements
     * ```php
     * // Permission entity configuration
     * $permission->require_active_membership = true;      // Require SCA membership
     * $permission->require_active_background_check = true; // Require background check
     * $permission->require_min_age = 18;                  // Require minimum age
     * $permission->requires_warrant = true;               // Require active warrant
     * ```
     *
     * ## Usage Examples
     *
     * ### Standard Permission Query
     * ```php
     * $query = $permissionsTable->find();
     * $query = PermissionsLoader::validPermissionClauses($query)
     *     ->where(['Members.id' => $memberId])
     *     ->contain(['PermissionPolicies']);
     * ```
     *
     * ### Member Discovery Query
     * ```php
     * $subquery = $permissionsTable->find();
     * $subquery = PermissionsLoader::validPermissionClauses($subquery)
     *     ->where(['Permissions.name' => 'manage_members'])
     *     ->select(['Members.id']);
     * ```
     *
     * ### Custom Authorization Check
     * ```php
     * $query = $permissionsTable->find();
     * $validatedQuery = PermissionsLoader::validPermissionClauses($query)
     *     ->where([
     *         'Permissions.id' => $permissionId,
     *         'Members.id' => $memberId,
     *         'MemberRoles.branch_id' => $branchId
     *     ]);
     * 
     * $hasPermission = $validatedQuery->count() > 0;
     * ```
     *
     * @param SelectQuery $q Base query to apply validation clauses to
     * @return SelectQuery Query with comprehensive validation chain applied
     * @see \App\Model\Entity\Member Member status constants and field definitions
     * @see \App\Model\Entity\Warrant Warrant status constants and validation rules
     * @see \App\Model\Entity\Permission Permission requirement flags
     * @see \App\KMP\StaticHelpers::getAppSetting() Configuration value retrieval
     */
    protected static function validPermissionClauses(SelectQuery $q): SelectQuery
    {
        // 1. Initialize Temporal Reference Point
        $now = DateTime::now(); // All temporal validations relative to current time

        // 2. Setup Warrant Validation Subquery (if enabled)
        $warrantsTable = TableRegistry::getTableLocator()->get('Warrants');

        // Build efficient subquery for warrant validation
        $warrantSubquery = $warrantsTable->find()
            ->select(['Warrants.member_role_id']) // Only need role linkage
            ->where([
                'Warrants.start_on <' => $now,           // Warrant has started
                'Warrants.expires_on >' => $now,         // Warrant hasn't expired
                'Warrants.status' => Warrant::CURRENT_STATUS, // Warrant is active
            ]);

        // 3. Apply Core Role-Member JOIN and Temporal Validation
        $q = $q->innerJoinWith('Roles.Members') // Efficient JOIN for role-member association
            ->where([
                // Role Assignment Temporal Validation
                'MemberRoles.start_on < ' => DateTime::now(), // Role assignment has started
                'OR' => [
                    'MemberRoles.expires_on IS ' => null,      // Permanent role assignment
                    'MemberRoles.expires_on >' => DateTime::now(), // Or hasn't expired
                ],
            ])

            // 4. Conditional Membership Status Validation
            ->where([
                'OR' => [
                    'Permissions.require_active_membership' => false, // Permission doesn't require membership
                    'AND' => [
                        // Permission requires membership - validate status and expiration
                        'Members.status IN ' => [
                            Member::STATUS_VERIFIED_MEMBERSHIP, // Full verified member
                            Member::STATUS_VERIFIED_MINOR,      // Verified minor member
                        ],
                        'Members.membership_expires_on >' => DateTime::now(), // Membership is current
                    ],
                ],
            ])

            // 5. Conditional Background Check Validation
            ->where([
                'OR' => [
                    'Permissions.require_active_background_check' => false, // Permission doesn't require background check
                    'Members.background_check_expires_on >' => DateTime::now(), // Or background check is current
                ],
            ])

            // 6. Complex Age-Based Access Control
            ->where([
                'OR' => [
                    'Permissions.require_min_age' => 0, // Permission has no age requirement
                    'AND' => [
                        // Edge case: Birthday this year but hasn't occurred yet
                        'Members.birth_year = ' . strval($now->year) . ' - Permissions.require_min_age',
                        'Members.birth_month <=' => $now->month, // Birthday has already occurred
                    ],
                    // Standard case: Birth year is before the required age cutoff
                    'Members.birth_year < ' . strval($now->year) . ' - Permissions.require_min_age',
                ],
            ]);

        // 7. Configurable Warrant Validation Layer
        $useWarrant = StaticHelpers::getAppSetting('KMP.RequireActiveWarrantForSecurity');
        if (strtolower($useWarrant) == 'yes') {
            $q = $q->where([
                'OR' => [
                    'Permissions.requires_warrant' => false, // Permission doesn't require warrant
                    'AND' => [
                        // Permission requires warrant - validate member eligibility and active warrant
                        'Members.warrantable' => true,              // Member is eligible for warrants
                        'MemberRoles.id IN' => $warrantSubquery,    // Role has active warrant
                    ],
                ],
            ]);
        }

        return $q; // Return query with complete validation chain applied
    }

    /**
     * Discover Application Policy Classes and Methods
     *
     * Scans application and plugin directories for policy classes and discovers
     * their authorization methods. Returns mapping of policy classes to method arrays.
     *
     * @return array Policy class to methods mapping
     */
    public static function getApplicationPolicies(): array
    {
        // 1. Policy Discovery - Identify policy directory paths
        $paths = [];

        // App policy folder: Core application policies in app/src/Policy
        $appPolicyPath = realpath(__DIR__ . '/../Policy');
        if ($appPolicyPath !== false) {
            $paths[] = $appPolicyPath; // Add if path exists and is valid
        }

        // Plugin policy folders: Discover policies in all plugin directories
        // Pattern: app/plugins/*/src/Policy
        $pluginPolicyDirs = glob(__DIR__ . '/../../plugins/*/src/Policy', GLOB_ONLYDIR);
        foreach ($pluginPolicyDirs as $dir) {
            $realDir = realpath($dir);
            if ($realDir !== false) {
                $paths[] = $realDir; // Add valid plugin policy directories
            }
        }

        // 2. Dynamic File Loading - Load all PHP files in policy directories
        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path), // Recursive directory scanning
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    require_once $file->getPathname(); // Load policy class files
                }
            }
        }

        // 3. Policy Class Discovery - Analyze all loaded classes via reflection
        $policyClasses = [];

        foreach (get_declared_classes() as $class) {
            try {
                $reflector = new ReflectionClass($class);
                $filename = $reflector->getFileName();
                if ($filename === false) {
                    continue; // Skip classes without file association
                }

                // Check if class has SKIP_BASE constant for inheritance control
                if (!defined($class . '::SKIP_BASE')) {
                    $skipBase = false; // Include inherited methods by default
                } else {
                    $skipBase = constant($class . '::SKIP_BASE'); // Use class-defined setting
                }

                // 4. Path Validation - Ensure class is in a policy directory
                foreach ($paths as $policyPath) {
                    if (strpos(realpath($filename), $policyPath) === 0) {
                        // Class is in a policy directory - analyze methods

                        // 5. Method Discovery - Find authorization methods
                        $methods = [];
                        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                            // Filter methods based on authorization patterns
                            if ($method->isPublic()) {
                                // Skip inherited methods if SKIP_BASE is true
                                if ($method->getDeclaringClass()->getName() != $class && $skipBase) {
                                    continue;
                                }

                                // Skip non-callable methods (static, constructor)
                                if ($method->isStatic() || $method->isConstructor()) {
                                    continue;
                                }

                                // Only include methods starting with 'can' (authorization methods)
                                $canResult = preg_match('/^can/', $method->getName());
                                if ($canResult === 0) {
                                    continue;
                                }

                                $methods[] = $method->getName(); // Add valid authorization method
                            }
                        }

                        // 6. Dynamic Method Support - Add programmatically defined methods
                        if (method_exists($class, 'getDynamicMethods')) {
                            $dynamicMethods = call_user_func([$class, 'getDynamicMethods']);
                            $methods = array_merge($methods, $dynamicMethods);
                        }

                        // 7. Class Validation and Registration
                        if (empty($methods)) {
                            continue; // Skip classes with no authorization methods
                        }

                        // Skip BasePolicy as it's the foundation class
                        if ($class == "App\Policy\BasePolicy") {
                            continue;
                        }

                        // Register policy class with its methods
                        $policyClasses[$class] = $methods;
                        break; // Stop checking other paths for this class
                    }
                }
            } catch (ReflectionException $e) {
                // Skip classes that cannot be reflected (handles edge cases gracefully)
                continue;
            }
        }

        return $policyClasses; // Return complete policy class to methods mapping
    }
}
