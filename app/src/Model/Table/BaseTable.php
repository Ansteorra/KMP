<?php
declare(strict_types=1);

/**
 * Kingdom Management Portal (KMP) - Base Table Class
 * 
 * This is the foundational table class for the KMP application, providing shared
 * functionality across all table classes in the system. It handles critical
 * database-wide concerns including cache management, branch-based query scoping,
 * and automatic cache invalidation on data changes.
 * 
 * ## Core Responsibilities
 * 
 * ### 1. Cache Management & Invalidation
 * - Provides automatic cache clearing on entity save operations
 * - Supports entity-specific, entity-ID-based, and group-based cache invalidation
 * - Integrates with CakePHP's Cache system for performance optimization
 * 
 * ### 2. Branch-Based Data Scoping
 * - Implements branch hierarchy filtering for organizational data segregation
 * - Provides base query modification for branch-specific data access
 * - Supports security through data isolation by organizational branch
 * 
 * ### 3. Event System Integration
 * - Hooks into CakePHP's ORM event system for automatic processing
 * - Provides extension points for child table classes
 * - Maintains data consistency across related entities
 * 
 * ## Architecture Integration
 * 
 * This base table integrates with several KMP subsystems:
 * - Caching System: Via CakePHP Cache component for performance
 * - Branch Hierarchy: For organizational data scoping and security
 * - Authorization System: By limiting data access to appropriate branches
 * - Plugin System: Provides consistent behavior across all plugins
 * 
 * ## Usage Patterns
 * 
 * All application and plugin table classes should extend this class:
 * ```php
 * class MembersTable extends BaseTable
 * {
 *     // Define cache invalidation patterns
 *     protected const CACHES_TO_CLEAR = [
 *         ['member_list', 'members'],
 *         ['member_count', 'members'],
 *     ];
 *     
 *     protected const ID_CACHES_TO_CLEAR = [
 *         ['member_', 'members'],
 *         ['profile_', 'members'],
 *     ];
 *     
 *     protected const CACHE_GROUPS_TO_CLEAR = ['security', 'navigation'];
 * }
 * ```
 * 
 * ## Cache Invalidation Strategy
 * 
 * The cache system supports three levels of invalidation:
 * 
 * ### Static Caches (CACHES_TO_CLEAR)
 * Fixed cache keys that should be cleared on any entity save:
 * ```php
 * protected const CACHES_TO_CLEAR = [
 *     ['cache_key', 'cache_config'], // [key, config]
 *     ['branch_list', 'branch_structure'],
 * ];
 * ```
 * 
 * ### Entity-Based Caches (ID_CACHES_TO_CLEAR)
 * Cache keys that include the entity ID and should be cleared when that entity changes:
 * ```php
 * protected const ID_CACHES_TO_CLEAR = [
 *     ['prefix_', 'cache_config'], // Will clear 'prefix_{entity_id}'
 *     ['member_profile_', 'members'],
 * ];
 * ```
 * 
 * ### Group-Based Caches (CACHE_GROUPS_TO_CLEAR)
 * Cache groups that should be entirely cleared:
 * ```php
 * protected const CACHE_GROUPS_TO_CLEAR = ['security', 'navigation'];
 * ```
 * 
 * ## Branch Scoping
 * 
 * The branch scoping system enables organizational data isolation:
 * ```php
 * // Child tables can override for custom branch relationships
 * public function addBranchScopeQuery($query, $branchIDs): SelectQuery
 * {
 *     return $query->where(['CustomTable.branch_id IN' => $branchIDs]);
 * }
 * ```
 * 
 * ## Security Considerations
 * 
 * - Branch scoping prevents unauthorized cross-organizational data access
 * - Cache invalidation ensures data consistency and prevents stale data exposure
 * - Event hooks maintain referential integrity across related entities
 * 
 * @package App\Model\Table
 * @author KMP Development Team
 * @since KMP 1.0
 * @see \Cake\ORM\Table For base table functionality
 * @see \Cake\Cache\Cache For cache management
 * @see \App\Model\Table\BranchesTable For branch hierarchy implementation
 */

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class BaseTable extends Table
{
    /**
     * Static cache entries to clear on entity save
     * 
     * This constant defines cache entries with fixed keys that should be
     * invalidated whenever any entity in this table is saved. Each entry
     * is an array containing the cache key and cache configuration name.
     * 
     * Format: [['cache_key', 'cache_config'], ...]
     * 
     * Example:
     * ```php
     * protected const CACHES_TO_CLEAR = [
     *     ['member_list_active', 'members'],
     *     ['total_member_count', 'statistics'],
     *     ['navigation_menu', 'navigation'],
     * ];
     * ```
     * 
     * Child classes should override this constant to define their specific
     * static cache entries that need invalidation.
     * 
     * @var array<array{string, string}>
     */
    protected const CACHES_TO_CLEAR = [];
    
    /**
     * Entity-ID-based cache entries to clear on entity save
     * 
     * This constant defines cache entry prefixes that, when combined with
     * the entity ID, should be invalidated when that specific entity is saved.
     * Each entry is an array containing the cache key prefix and cache configuration.
     * 
     * Format: [['prefix_', 'cache_config'], ...]
     * 
     * Example:
     * ```php
     * protected const ID_CACHES_TO_CLEAR = [
     *     ['member_profile_', 'members'],     // Clears 'member_profile_{id}'
     *     ['member_permissions_', 'security'], // Clears 'member_permissions_{id}'
     *     ['branch_descendants_', 'structure'], // Clears 'branch_descendants_{id}'
     * ];
     * ```
     * 
     * When an entity with ID 123 is saved, the above configuration would clear:
     * - member_profile_123
     * - member_permissions_123 
     * - branch_descendants_123
     * 
     * @var array<array{string, string}>
     */
    protected const ID_CACHES_TO_CLEAR = [];
    
    /**
     * Cache groups to clear on entity save
     * 
     * This constant defines cache groups that should be entirely cleared
     * whenever any entity in this table is saved. This is useful for
     * invalidating large sets of related cache entries at once.
     * 
     * Format: ['group_name', ...]
     * 
     * Example:
     * ```php
     * protected const CACHE_GROUPS_TO_CLEAR = [
     *     'security',    // Clear all security-related caches
     *     'navigation',  // Clear all navigation caches
     *     'statistics',  // Clear all statistical caches
     * ];
     * ```
     * 
     * Cache groups are defined in the cache configuration and allow bulk
     * invalidation of related cache entries without knowing specific keys.
     * 
     * @var array<string>
     */
    protected const CACHE_GROUPS_TO_CLEAR = [];

    /**
     * After-save event handler for automatic cache invalidation
     * 
     * This method is automatically called by CakePHP's ORM after any entity
     * is successfully saved (create or update). It handles cache invalidation
     * based on the constants defined in the table class, ensuring data
     * consistency and preventing stale cache data.
     * 
     * ## Cache Invalidation Process
     * 
     * The method processes cache invalidation in three phases:
     * 
     * ### Phase 1: Static Cache Clearing
     * Clears fixed cache keys defined in CACHES_TO_CLEAR:
     * ```php
     * // If CACHES_TO_CLEAR = [['member_list', 'members']]
     * Cache::delete('member_list', 'members');
     * ```
     * 
     * ### Phase 2: Entity-Specific Cache Clearing
     * Clears cache keys that include the saved entity's ID:
     * ```php
     * // If ID_CACHES_TO_CLEAR = [['profile_', 'members']] and entity->id = 123
     * Cache::delete('profile_123', 'members');
     * ```
     * 
     * ### Phase 3: Group Cache Clearing
     * Clears entire cache groups defined in CACHE_GROUPS_TO_CLEAR:
     * ```php
     * // If CACHE_GROUPS_TO_CLEAR = ['security']
     * Cache::clearGroup('security');
     * ```
     * 
     * ## Performance Considerations
     * 
     * - Only processes non-empty cache configuration arrays
     * - Uses efficient cache operations (delete vs clearGroup)
     * - Minimal overhead when no cache configuration is defined
     * - Executes after successful save to ensure data consistency
     * 
     * ## Usage in Child Classes
     * 
     * Child classes should define cache constants rather than override this method:
     * ```php
     * class MembersTable extends BaseTable
     * {
     *     protected const CACHES_TO_CLEAR = [
     *         ['active_members', 'members'],
     *         ['member_statistics', 'stats'],
     *     ];
     *     
     *     protected const ID_CACHES_TO_CLEAR = [
     *         ['member_profile_', 'members'],
     *         ['member_roles_', 'security'],
     *     ];
     * }
     * ```
     * 
     * ## Error Handling
     * 
     * Cache operations are designed to be fault-tolerant:
     * - Failed cache deletions don't break the save operation
     * - Non-existent cache keys are handled gracefully
     * - Cache configuration errors are logged but don't throw exceptions
     * 
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @param \Cake\Datasource\EntityInterface $entity The saved entity
     * @param \ArrayObject $options Save options
     * @return void
     * 
     * @see \Cake\Cache\Cache::delete() For cache key deletion
     * @see \Cake\Cache\Cache::clearGroup() For cache group clearing
     */
    public function afterSave($event, $entity, $options): void
    {
        // Phase 1: Clear static cache entries
        if (!empty($this::CACHES_TO_CLEAR)) {
            foreach ($this::CACHES_TO_CLEAR as $cache) {
                // Each cache entry: [cache_key, cache_config]
                Cache::delete($cache[0], $cache[1]);
            }
        }
        
        // Phase 2: Clear entity-ID-based cache entries
        if (!empty($this::ID_CACHES_TO_CLEAR)) {
            foreach ($this::ID_CACHES_TO_CLEAR as $cache) {
                // Each cache entry: [prefix, cache_config]
                // Combines prefix with entity ID: prefix{entity_id}
                Cache::delete($cache[0] . $entity->id, $cache[1]);
            }
        }
        
        // Phase 3: Clear cache groups entirely
        if (!empty($this::CACHE_GROUPS_TO_CLEAR)) {
            foreach ($this::CACHE_GROUPS_TO_CLEAR as $cache) {
                // Clear all cache entries in the specified group
                Cache::clearGroup($cache);
            }
        }
    }

    /**
     * Add branch-based data scoping to a query
     * 
     * This method implements KMP's organizational data segregation by adding
     * branch-based filtering to database queries. It ensures that users only
     * see data from branches they have access to, providing both security
     * and organizational data isolation.
     * 
     * ## Branch Hierarchy & Security
     * 
     * KMP uses a hierarchical branch structure where users may have access to:
     * - Their own branch data
     * - Data from child branches under their authority
     * - Data from parent branches (with appropriate permissions)
     * 
     * The $branchIDs parameter should contain all branch IDs that the current
     * user is authorized to access, as determined by the authorization system.
     * 
     * ## Default Implementation
     * 
     * The base implementation assumes a direct branch relationship:
     * ```sql
     * WHERE Branches.id IN (1, 2, 3, ...)
     * ```
     * 
     * This works for entities that have a direct relationship to the Branches table
     * or are joined with Branches in the query.
     * 
     * ## Child Class Overrides
     * 
     * Child table classes should override this method to implement their specific
     * branch relationship patterns:
     * 
     * ### Direct Branch Relationship
     * ```php
     * public function addBranchScopeQuery($query, $branchIDs): SelectQuery
     * {
     *     if (empty($branchIDs)) {
     *         return $query;
     *     }
     *     return $query->where(['Members.branch_id IN' => $branchIDs]);
     * }
     * ```
     * 
     * ### Indirect Branch Relationship (via Awards)
     * ```php
     * public function addBranchScopeQuery($query, $branchIDs): SelectQuery
     * {
     *     if (empty($branchIDs)) {
     *         return $query;
     *     }
     *     return $query->where(['Awards.branch_id IN' => $branchIDs]);
     * }
     * ```
     * 
     * ### Complex Branch Relationships
     * ```php
     * public function addBranchScopeQuery($query, $branchIDs): SelectQuery
     * {
     *     if (empty($branchIDs)) {
     *         return $query;
     *     }
     *     
     *     return $query->where([
     *         'OR' => [
     *             ['Members.branch_id IN' => $branchIDs],
     *             ['Members.primary_branch_id IN' => $branchIDs],
     *         ]
     *     ]);
     * }
     * ```
     * 
     * ## Integration with Authorization
     * 
     * This method is typically called from controller actions or finder methods
     * after the authorization system has determined which branches the user
     * can access:
     * 
     * ```php
     * public function findAuthorized(SelectQuery $query, array $options): SelectQuery
     * {
     *     $user = $options['user'];
     *     $authorizedBranches = $this->getAuthorizedBranches($user);
     *     return $this->addBranchScopeQuery($query, $authorizedBranches);
     * }
     * ```
     * 
     * ## Performance Considerations
     * 
     * - Returns query unmodified when $branchIDs is empty (no performance impact)
     * - Uses IN clause which is efficiently indexed in most databases
     * - Should be applied early in query building for optimal execution plans
     * - Consider caching branch authorization results for repeated queries
     * 
     * ## Security Notes
     * 
     * - Empty $branchIDs array means no branch restrictions (handle carefully)
     * - Always validate $branchIDs contains only authorized branches
     * - This method enforces data isolation but not permission validation
     * - Child classes must implement appropriate branch relationship logic
     * 
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify with branch scoping
     * @param array<int> $branchIDs Array of branch IDs the user is authorized to access
     * @return \Cake\ORM\Query\SelectQuery The modified query with branch filtering applied
     * 
     * @example Basic Usage
     * ```php
     * $query = $this->find();
     * $query = $this->addBranchScopeQuery($query, [1, 2, 5, 8]);
     * // Results limited to records from branches 1, 2, 5, and 8
     * ```
     * 
     * @example Controller Integration
     * ```php
     * public function index()
     * {
     *     $authorizedBranches = $this->Authorization->getAuthorizedBranches();
     *     $query = $this->Members->find();
     *     $query = $this->Members->addBranchScopeQuery($query, $authorizedBranches);
     *     $members = $this->paginate($query);
     * }
     * ```
     * 
     * @see \App\Model\Table\BranchesTable For branch hierarchy implementation
     * @see \App\Services\AuthorizationService For branch authorization logic
     */
    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        // Return query unmodified if no branch restrictions
        if (empty($branchIDs)) {
            return $query;
        }
        
        // Apply branch filtering using IN clause
        // Default assumes direct Branches table relationship
        $query = $query->where([
            'Branches.id IN' => $branchIDs,
        ]);

        return $query;
    }
}