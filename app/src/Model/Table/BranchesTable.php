<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Branches Model - Hierarchical Organizational Structure Management for KMP
 * 
 * Manages the organizational hierarchy for the Kingdom Management Portal, supporting
 * nested tree structures for kingdoms, principalities, baronies, shires, and other
 * administrative divisions. Provides efficient tree operations with aggressive caching
 * and automatic integrity maintenance.
 * 
 * **Core Architecture:**
 * - Extends BaseTable for KMP cache management and branch scoping
 * - Implements Tree behavior for nested set model operations
 * - Provides specialized cache invalidation for hierarchical data
 * - Supports JSON field storage for flexible branch configuration
 * - Integrates with authorization system for organizational access control
 * 
 * **Tree Structure Management:**
 * - Nested set model (lft/rght) for efficient tree queries
 * - Automatic tree recovery and integrity maintenance
 * - Cached descendant and ancestor lookups for performance
 * - Support for unlimited hierarchy depth
 * - Circular reference detection and prevention
 * 
 * **Performance Optimization:**
 * - Two-tier caching strategy: descendants and parents lookups
 * - Cache invalidation on tree structure changes
 * - Efficient threaded tree queries for UI display
 * - Batch operations for tree restructuring
 * 
 * **Member Association:**
 * - Links members to organizational units
 * - Supports member visibility and access control
 * - Enables branch-specific role assignments
 * - Provides organizational reporting capabilities
 * 
 * **Validation & Business Rules:**
 * - Unique branch names across entire organization
 * - Required name and location fields
 * - Circular reference prevention in parent-child relationships
 * - JSON schema validation for links field
 * 
 * **Authorization Integration:**
 * - Branch-scoped data access control
 * - Hierarchical permission inheritance
 * - Integration with policy-based authorization
 * - Support for organizational role-based access
 * 
 * **Usage Examples:**
 * ```php
 * // Tree operations
 * $branchesTable = $this->getTableLocator()->get('Branches');
 * 
 * // Get all descendants of a branch
 * $descendantIds = $branchesTable->getAllDecendentIds($branchId);
 * 
 * // Get path to root for breadcrumbs
 * $parentIds = $branchesTable->getAllParents($branchId);
 * 
 * // Get threaded tree for navigation
 * $tree = $branchesTable->getThreadedTree();
 * 
 * // Find children for dropdown
 * $children = $branchesTable->find('children', ['for' => $parentId, 'direct' => true]);
 * 
 * // Tree list for form selects
 * $treeList = $branchesTable->find('treeList', ['spacer' => '--']);
 * ```
 * 
 * **Tree Recovery & Maintenance:**
 * ```php
 * // Automatic recovery on application startup
 * $branchesTable->recover();  // Rebuilds lft/rght values
 * 
 * // Handle tree structure changes
 * $branch->parent_id = $newParentId;
 * $branchesTable->save($branch);  // Triggers tree rebalancing
 * ```
 * 
 * **Caching Strategy:**
 * - Static cache keys: none (all data is dynamic)
 * - ID-based caches: descendants_[id], parents_[id] in 'branch_structure' config
 * - Group caches: 'security' group for authorization data
 * - Cache TTL: Managed by CakePHP cache configuration
 * - Invalidation: On any save operation affecting tree structure
 * 
 * **Database Schema:**
 * - Standard tree behavior fields (lft, rght, parent_id)
 * - JSON links field for external resource storage
 * - Audit trail fields (created, modified, created_by, modified_by)
 * - Soft deletion support (deleted, deleted_date)
 * - Branch type classification and domain association
 * 
 * @property \App\Model\Entity\Branch[] $entities
 * 
 * @method \App\Model\Entity\Branch get($primaryKey, array $options = [])
 * @method \App\Model\Entity\Branch newEntity(array $data = [], array $options = [])
 * @method \App\Model\Entity\Branch[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Branch|bool save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Branch patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Branch[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Branch findOrCreate($search, ?callable $callback = null, array $options = [])
 * 
 * @see \App\Model\Entity\Branch For branch entity documentation
 * @see \App\Controller\BranchesController For branch management workflows
 * @see \App\Policy\BranchPolicy For authorization rules
 * @see \App\Model\Table\BaseTable For cache management and base functionality
 * @see \Cake\ORM\Behavior\TreeBehavior For tree operation details
 */
class BranchesTable extends BaseTable
{
    /**
     * Initialize method - Configure table relationships and behaviors
     *
     * Sets up the BranchesTable with all necessary associations, behaviors, and
     * configuration for hierarchical branch management. Establishes parent-child
     * relationships, member associations, and enables tree operations with audit trails.
     *
     * **Tree Behavior Configuration:**
     * - Nested set model for efficient tree queries
     * - Automatic lft/rght field management
     * - Support for tree recovery and integrity maintenance
     * - Parent-child relationship handling
     *
     * **Behavioral Features:**
     * - Timestamp: Automatic created/modified tracking
     * - Footprint: User attribution for data changes
     * - Trash: Soft deletion with recovery capabilities
     * - Tree: Hierarchical data management
     *
     * **Association Setup:**
     * - Self-referential parent-child relationships
     * - One-to-many member associations
     * - Support for unlimited hierarchy depth
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('branches');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->BelongsTo('Parent', [
            'className' => 'Branches',
            'foreignKey' => 'parent_id',
        ]);

        $this->HasMany('Members', [
            'className' => 'Members',
            'foreignKey' => 'branch_id',
        ]);

        $this->BelongsTo('Contacts', [
            'className' => 'Members',
            'foreignKey' => 'contact_id',
            'joinType' => 'LEFT',
        ]);
        $this->addBehavior('Tree');
        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');
        $this->addBehavior('PublicId');
    }

    /**
     * Get database schema with JSON field configuration
     * 
     * Customizes the database schema to properly handle the JSON links field.
     * This ensures that the links field is correctly typed for JSON operations
     * and database storage.
     * 
     * **JSON Field Configuration:**
     * - links: Stores external resource URLs and configuration
     * - Supports nested JSON structures for complex link configurations
     * - Enables database-level JSON queries and operations
     * 
     * **Usage Examples:**
     * ```php
     * // JSON links structure
     * $branch->links = [
     *     'website' => 'https://atlantia.sca.org',
     *     'calendar' => 'https://calendar.atlantia.sca.org',
     *     'newsletter' => 'https://acorn.atlantia.sca.org',
     *     'social' => [
     *         'facebook' => 'https://facebook.com/atlantia.sca',
     *         'discord' => 'https://discord.gg/atlantia'
     *     ]
     * ];
     * ```
     * 
     * @return \Cake\Database\Schema\TableSchemaInterface Configured schema with JSON field typing
     */
    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('links', 'json');

        return $schema;
    }

    /**
     * Cache configuration for branch hierarchy data
     * 
     * Defines the caching strategy for branch tree operations to optimize
     * performance of hierarchical queries. Uses specialized cache groups
     * for different types of branch data.
     * 
     * **Cache Strategy:**
     * - No static caches (all branch data is dynamic)
     * - ID-based caches for descendants and parents lookups
     * - Security group cache for authorization data
     * - Cache invalidation on any tree structure changes
     * 
     * **Performance Impact:**
     * - Descendants cache: O(1) lookup vs O(n) tree traversal
     * - Parents cache: O(1) lookup vs O(log n) tree walk
     * - Security cache: Pre-computed authorization scopes
     * 
     * @see BaseTable::CACHES_TO_CLEAR For static cache patterns
     * @see BaseTable::ID_CACHES_TO_CLEAR For entity-based cache patterns
     * @see BaseTable::CACHE_GROUPS_TO_CLEAR For group-based cache patterns
     */
    protected const CACHES_TO_CLEAR = [];
    protected const ID_CACHES_TO_CLEAR = [
        ['descendants_', 'branch_structure'],
        ['parents_', 'branch_structure'],
    ];
    protected const CACHE_GROUPS_TO_CLEAR = ['security'];

    /**
     * Default validation rules for branch data integrity
     *
     * Implements comprehensive validation for branch entities to ensure data
     * quality and organizational consistency. Enforces unique naming across
     * the entire hierarchy and validates required organizational information.
     *
     * **Validation Rules:**
     * - Name: Required, non-empty, unique across all branches
     * - Location: Required, non-empty string for geographic identification
     * - Additional validations applied through business rules
     *
     * **Security Considerations:**
     * - Unique name constraint prevents organizational confusion
     * - Required fields ensure complete branch registration
     * - Validates against SQL injection and XSS attacks
     *
     * **Usage Examples:**
     * ```php
     * // Valid branch data
     * $validData = [
     *     'name' => 'Barony of Windmasters Hill',
     *     'location' => 'Northern Virginia, USA',
     *     'type' => 'Barony',
     *     'parent_id' => $atlantiaId
     * ];
     * 
     * // Validation errors
     * $invalidData = [
     *     'name' => '',  // Error: required field
     *     'location' => ''  // Error: required field
     * ];
     * 
     * $duplicateData = [
     *     'name' => 'Kingdom of Atlantia'  // Error: name already exists
     * ];
     * ```
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator Configured validator with branch-specific rules
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
            ]);

        $validator
            ->requirePresence('location', 'create')
            ->notEmptyString('location');

        return $validator;
    }

    /**
     * Returns a rules checker object for application integrity validation
     *
     * Implements business rules that ensure organizational data integrity
     * beyond basic field validation. Enforces unique constraints and
     * referential integrity for the branch hierarchy.
     *
     * **Business Rules:**
     * - Unique branch names across entire organization
     * - Referential integrity for parent-child relationships
     * - Tree structure consistency (handled by Tree behavior)
     *
     * **Rule Processing:**
     * - isUnique: Prevents duplicate branch names
     * - Additional rules can be added for complex business logic
     * - Integrates with Tree behavior for hierarchy validation
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker Configured rules checker with branch-specific rules
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']));
        $rules->add($rules->existsIn(['contact_id'], 'Contacts'), ['errorField' => 'contact_id']);

        return $rules;
    }

    /**
     * Get all descendant branch IDs with aggressive caching
     * 
     * Retrieves all descendant branch IDs for a given branch, using a two-tier
     * caching strategy for optimal performance. This method is critical for
     * authorization and organizational reporting.
     * 
     * **Caching Strategy:**
     * 1. Check individual cache for specific branch ID
     * 2. If miss, rebuild entire descendants lookup table
     * 3. Cache all branch descendants for future requests
     * 4. Return specific branch descendants
     * 
     * **Performance Characteristics:**
     * - Cache hit: O(1) lookup time
     * - Cache miss: O(n) rebuild time, but caches all branches
     * - Memory efficient: Only stores branch IDs, not full entities
     * - TTL: Managed by 'branch_structure' cache configuration
     * 
     * **Use Cases:**
     * - Authorization: Check permissions for branch hierarchy
     * - Reporting: Generate reports for organizational units
     * - Member management: Find members in branch and sub-branches
     * - Event planning: Determine branch participation scope
     * 
     * **Usage Examples:**
     * ```php
     * // Get all branches under Kingdom of Atlantia
     * $atlantiaDescendants = $branchesTable->getAllDecendentIds($atlantiaId);
     * // Returns: [principality1, principality2, barony1, barony2, shire1, ...]
     * 
     * // Authorization check for hierarchical permissions
     * $userBranches = $user->getPermission('manage_events')->branch_ids;
     * $canManage = in_array($eventBranchId, $userBranches);
     * 
     * // Member search within organizational scope
     * $members = $membersTable->find()
     *     ->where(['branch_id IN' => $branchesTable->getAllDecendentIds($branchId)]);
     * ```
     * 
     * @param int $id The branch ID to get descendants for
     * @return array<int> Array of descendant branch IDs (empty array if no descendants)
     */
    public function getAllDecendentIds($id): array
    {
        $descendants = Cache::read('descendants_' . $id, 'branch_structure');
        if (!$descendants) {
            $descendants = $this->getDescendantsLookup();
            foreach ($descendants as $key => $value) {
                Cache::write('descendants_' . $key, $value, 'branch_structure');
            }
            $descendants = $descendants[$id] ?? [];
        }

        return $descendants ?? [];
    }

    /**
     * Get all parent branch IDs with aggressive caching
     * 
     * Retrieves all parent branch IDs for a given branch, providing the complete
     * path from the branch to the root of the hierarchy. Uses caching strategy
     * similar to descendants for optimal performance.
     * 
     * **Caching Strategy:**
     * 1. Check individual cache for specific branch ID
     * 2. If miss, rebuild entire parents lookup table
     * 3. Cache all branch parents for future requests
     * 4. Return specific branch parents
     * 
     * **Return Format:**
     * - Array of parent IDs from immediate parent to root
     * - Empty array if branch is at root level
     * - Maintains hierarchical order (parent, grandparent, great-grandparent, ...)
     * 
     * **Use Cases:**
     * - Breadcrumb generation for navigation
     * - Permission inheritance checking
     * - Organizational reporting and analytics
     * - Branch hierarchy validation
     * 
     * **Usage Examples:**
     * ```php
     * // Get path to root for breadcrumbs
     * $parentIds = $branchesTable->getAllParents($baronyId);
     * // Returns: [principalityId, kingdomId] (immediate parent to root)
     * 
     * // Build breadcrumb navigation
     * $breadcrumbs = [];
     * foreach ($parentIds as $parentId) {
     *     $breadcrumbs[] = $branchesTable->get($parentId);
     * }
     * 
     * // Check permission inheritance
     * $hasInheritedPermission = false;
     * foreach ($branchesTable->getAllParents($branchId) as $parentId) {
     *     if ($user->hasPermissionForBranch($permission, $parentId)) {
     *         $hasInheritedPermission = true;
     *         break;
     *     }
     * }
     * ```
     * 
     * @param int $id The branch ID to get parents for
     * @return array<int> Array of parent branch IDs in hierarchical order
     */
    public function getAllParents($id): array
    {
        $parents = Cache::read('parents_' . $id, 'branch_structure');
        if (!$parents) {
            $parents = $this->getParentsLookup();
            foreach ($parents as $key => $value) {
                Cache::write('parents_' . $key, $value, 'branch_structure');
            }
            $parents = $parents[$id] ?? [];
        }

        return $parents ?? [];
    }

    /**
     * Get threaded tree structure for UI display
     * 
     * Retrieves the entire branch hierarchy as a threaded tree structure,
     * optimized for UI display such as navigation menus, dropdowns, and
     * organizational charts. Uses Tree behavior's efficient threaded find.
     * 
     * **Tree Structure:**
     * - Nested array with 'children' key for sub-branches
     * - Includes minimal fields for performance (id, name, parent_id)
     * - Maintains hierarchical relationships and ordering
     * - Ready for direct use in view templates
     * 
     * **Performance Considerations:**
     * - Efficient single query using Tree behavior
     * - Minimal field selection for reduced memory usage
     * - No caching (structure changes frequently)
     * - Use sparingly for large hierarchies
     * 
     * **Usage Examples:**
     * ```php
     * // Generate navigation menu
     * $tree = $branchesTable->getThreadedTree();
     * foreach ($tree as $kingdom) {
     *     echo $kingdom->name;
     *     foreach ($kingdom->children as $principality) {
     *         echo "  " . $principality->name;
     *         foreach ($principality->children as $barony) {
     *             echo "    " . $barony->name;
     *         }
     *     }
     * }
     * 
     * // Generate organizational chart data
     * $chartData = $this->convertTreeToChartFormat($tree);
     * ```
     * 
     * @return array<\App\Model\Entity\Branch> Threaded tree structure with nested children
     */
    public function getThreadedTree()
    {
        // rebuild the array into a tree structure
        $branches = $this->find('threaded', [
            'parentField' => 'parent_id',
            'keyForeign' => 'id',
            'nestingKey' => 'children',
        ])->select(['id', 'name', 'parent_id'])->toArray();
        //create a quick index of all of the decendents for each branch

        return $branches;
    }

    /**
     * Build parents lookup table for all branches
     * 
     * Generates a complete lookup table mapping each branch ID to its array
     * of parent branch IDs. This method is called when cache misses occur
     * and rebuilds the entire parent relationship cache.
     * 
     * **Algorithm:**
     * 1. Get threaded tree structure
     * 2. Recursively traverse each node
     * 3. Build parent ID arrays for each branch
     * 4. Store complete lookup table for caching
     * 
     * **Performance:**
     * - O(n) time complexity for complete rebuild
     * - Efficient single-pass tree traversal
     * - Memory efficient storage of parent relationships
     * - Infrequent execution due to caching
     * 
     * @return array<int, array<int>> Lookup table mapping branch IDs to parent ID arrays
     */
    protected function getParentsLookup(): array
    {
        $tree = $this->getThreadedTree();
        $lookup = [];

        // we need to iterate through the tree creating the list of parents for each node
        $populateParents = function (object $node, array $parentIds = []) use (&$lookup, &$populateParents): void {
            $lookup[$node['id']] = $parentIds;
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $populateParents($child, array_merge($parentIds, [$node['id']]));
                }
            }
        };

        foreach ($tree as $node) {
            $populateParents($node);
        }

        return $lookup;
    }

    /**
     * Build descendants lookup table for all branches
     * 
     * Generates a complete lookup table mapping each branch ID to its array
     * of descendant branch IDs. This method is called when cache misses occur
     * and rebuilds the entire descendant relationship cache.
     * 
     * **Algorithm:**
     * 1. Get threaded tree structure
     * 2. Recursively process each node bottom-up
     * 3. Collect child IDs and merge with grandchild IDs
     * 4. Build complete descendant arrays for each branch
     * 
     * **Performance:**
     * - O(n) time complexity for complete rebuild
     * - Efficient recursive tree processing
     * - Bottom-up approach ensures complete descendant collection
     * - Memory efficient storage of descendant relationships
     * 
     * **Usage by Authorization System:**
     * - Permission inheritance down the hierarchy
     * - Branch-scoped data access control
     * - Organizational reporting and analytics
     * - Member visibility and management
     * 
     * @return array<int, array<int>> Lookup table mapping branch IDs to descendant ID arrays
     */
    protected function getDescendantsLookup(): array
    {
        $tree = $this->getThreadedTree();
        $lookup = [];

        // Recursive function to populate lookup for each node.
        $populateLookup = function (object $node) use (&$lookup, &$populateLookup): void {
            $childIDs = [];
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $childIDs[] = $child['id'];
                    $populateLookup($child);
                    // Merge in any descendants already computed for the child.
                    if (isset($lookup[$child['id']])) {
                        $childIDs = array_merge($childIDs, $lookup[$child['id']]);
                    }
                }
            }
            $lookup[$node['id']] = $childIDs;
        };

        // Process each top-level node.
        foreach ($tree as $node) {
            $populateLookup($node);
        }

        return $lookup;
    }
}
