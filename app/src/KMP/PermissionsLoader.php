<?php

declare(strict_types=1);

namespace App\KMP;

use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Model\Entity\ServicePrincipal;
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
 * Core RBAC security engine for KMP permission validation.
 *
 * Validates permissions through member roles, temporal boundaries, warrant requirements,
 * and policy framework integration. Implements multi-tier caching for performance.
 *
 * @see /docs/4.4-rbac-security-architecture.md For complete RBAC documentation
 */
class PermissionsLoader
{

    /**
     * Get complete permissions set for member.
     *
     * Loads all permissions with role validation, temporal boundaries, and policy integration.
     * Results are cached with key `member_permissions{memberId}`.
     *
     * @param int $memberId The member ID to load permissions for
     * @return array Associative array of permission objects indexed by permission ID
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
     * Get policy framework mappings for member.
     *
     * Extracts policy class/method mappings from permissions with branch scoping support.
     * Results are cached with key `permissions_policies{memberId}`.
     *
     * @param int $id Member ID to get policies for
     * @param array|null $branchIds Optional array of branch IDs to filter policies by
     * @return array Nested array of policy classes, methods, and authorization data
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
     * Get query for members with specific permission in branch context.
     *
     * Reverse permission lookup - finds all members who have a permission.
     * Respects permission scoping rules (global, branch-only, branch-and-children).
     *
     * @param int $permissionId The permission ID to search for
     * @param int $branch_id The branch context for scoped permission checking
     * @return SelectQuery Query object ready for execution or further modification
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
     * Apply comprehensive permission validation chain.
     *
     * Core RBAC security logic validating: role temporal boundaries, membership status,
     * background checks, age restrictions, and warrant requirements (when enabled).
     * Used by all permission checking operations for consistent validation.
     *
     * @param SelectQuery $q Base query to apply validation clauses to
     * @return SelectQuery Query with validation chain applied
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
                // Revocation Check - exclude explicitly revoked roles
                'MemberRoles.revoker_id IS' => null,
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

    /**
     * Get complete permissions set for a service principal.
     *
     * Loads all permissions through ServicePrincipalRoles with role validation and 
     * temporal boundaries. Similar to getPermissions() but for service principals.
     * Results are cached with key `sp_permissions_{servicePrincipalId}`.
     *
     * @param int $servicePrincipalId The service principal ID to load permissions for
     * @return array Associative array of permission objects indexed by permission ID
     */
    public static function getServicePrincipalPermissions(int $servicePrincipalId): array
    {
        // 1. Cache Strategy - Check for cached permissions first
        $cacheKey = 'sp_permissions_' . $servicePrincipalId;
        $cache = Cache::read($cacheKey, 'member_permissions');
        if ($cache) {
            return $cache;
        }

        // 2. Initialize Table Locators
        $branchTable = TableRegistry::getTableLocator()->get('Branches');
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $now = DateTime::now();

        // 3. Build Permission Query for Service Principals
        $query = $permissionsTable->find()
            ->innerJoinWith('Roles.ServicePrincipalRoles.ServicePrincipals')
            ->select([
                'Permissions.id',
                'Permissions.name',
                'Permissions.scoping_rule',
                'Permissions.is_super_user',
                'ServicePrincipalRoles.branch_id',
                'ServicePrincipalRoles.entity_id',
                'ServicePrincipalRoles.entity_type',
            ])
            ->contain(['PermissionPolicies'])
            ->where([
                'ServicePrincipals.id' => $servicePrincipalId,
                'ServicePrincipals.is_active' => true,
                // Temporal validation for role assignments
                'ServicePrincipalRoles.start_on <=' => $now,
                'OR' => [
                    'ServicePrincipalRoles.expires_on IS' => null,
                    'ServicePrincipalRoles.expires_on >=' => $now,
                ],
                'ServicePrincipalRoles.revoked_on IS' => null,
            ])
            ->distinct()
            ->all()
            ->toArray();

        // 4. Permission Merging and Scoping Logic (same as member permissions)
        $permissions = [];

        foreach ($query as $permission) {
            $branch_id = $permission->_matchingData['ServicePrincipalRoles']->branch_id;
            $entity_id = $permission->_matchingData['ServicePrincipalRoles']->entity_id;
            $entity_type = $permission->_matchingData['ServicePrincipalRoles']->entity_type;

            if (isset($permissions[$permission->id])) {
                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        $permissions[$permission->id]->branch_ids[] = $branch_id;
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id;
                        $idList = array_merge(
                            $permissions[$permission->id]->branch_ids,
                            $decendents,
                        );
                        $permissions[$permission->id]->branch_ids = array_unique($idList);
                        break;
                }
            } else {
                $permissions[$permission->id] = (object)[
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'scoping_rule' => $permission->scoping_rule,
                    'is_super_user' => $permission->is_super_user,
                    'branch_ids' => [],
                    'entity_id' => $entity_id,
                    'entity_type' => $entity_type,
                ];

                if ($permission->permission_policies) {
                    foreach ($permission->permission_policies as $policy) {
                        $permissions[$permission->id]->policies[$policy->policy_class][$policy->policy_method] = $policy->id;
                    }
                }

                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        $permissions[$permission->id]->branch_ids = null;
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        $permissions[$permission->id]->branch_ids = [$branch_id];
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id;
                        $permissions[$permission->id]->branch_ids = $decendents;
                        break;
                }
            }
        }

        // 5. Cache Result
        Cache::write($cacheKey, $permissions, 'member_permissions');

        return $permissions;
    }

    /**
     * Get policy framework mappings for a service principal.
     *
     * Similar to getPolicies() but for service principals.
     * Results are cached with key `sp_policies_{servicePrincipalId}`.
     *
     * @param int $servicePrincipalId Service principal ID
     * @param array|null $branchIds Optional branch IDs to filter policies
     * @return array Nested array of policy classes, methods, and authorization data
     */
    public static function getServicePrincipalPolicies(int $servicePrincipalId, ?array $branchIds = null): array
    {
        // 1. Cache Strategy
        $cacheKey = 'sp_policies_' . $servicePrincipalId;
        $cache = Cache::read($cacheKey, 'member_permissions');
        if ($cache) {
            return $cache;
        }

        // 2. Load Base Permissions
        $permissions = self::getServicePrincipalPermissions($servicePrincipalId);
        $policies = [];

        // 3. Extract Policy Mappings (same logic as member policies)
        foreach ($permissions as $permission) {
            if (isset($permission->policies)) {
                foreach ($permission->policies as $policyClass => $methods) {
                    if (!isset($policies[$policyClass])) {
                        $policies[$policyClass] = [];
                    }

                    foreach ($methods as $method => $policyId) {
                        if (!isset($policies[$policyClass][$method])) {
                            $policies[$policyClass][$method] = (object)[
                                'scoping_rule' => $permission->scoping_rule,
                                'branch_ids' => $permission->branch_ids,
                                'entity_id' => $permission->entity_id,
                                'entity_type' => $permission->entity_type,
                            ];
                        } else {
                            if ($permission->scoping_rule == Permission::SCOPE_GLOBAL) {
                                $policies[$policyClass][$method]->branch_ids = null;
                                $policies[$policyClass][$method]->scoping_rule = Permission::SCOPE_GLOBAL;
                            } elseif ($policies[$policyClass][$method]->scoping_rule != Permission::SCOPE_GLOBAL) {
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

        // 4. Branch ID Cleanup
        foreach ($policies as $policyClass => $methods) {
            foreach ($methods as $method => $policy) {
                if ($policy->branch_ids) {
                    $policy->branch_ids = array_unique($policy->branch_ids);
                }
            }
        }

        // 5. Optional Branch Filtering
        if ($branchIds) {
            foreach ($policies as $policyClass => $methods) {
                foreach ($methods as $method => $policy) {
                    if ($policy->branch_ids) {
                        $policy->branch_ids = array_intersect($policy->branch_ids, $branchIds);
                    }
                }
            }
        }

        // 6. Remove Empty Policies
        foreach ($policies as $policyClass => $methods) {
            foreach ($methods as $method => $policy) {
                if (empty($policy->branch_ids) && $policy->scoping_rule != Permission::SCOPE_GLOBAL) {
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

        // 8. Cache Result
        Cache::write($cacheKey, $policies, 'member_permissions');

        return $policies;
    }
}
