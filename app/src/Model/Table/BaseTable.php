<?php

declare(strict_types=1);

/**
 * BaseTable - Foundational table class for KMP application.
 *
 * Provides cache management, branch-based query scoping, and event hooks.
 *
 * Cache invalidation via class constants:
 * - CACHES_TO_CLEAR: Static cache keys
 * - ID_CACHES_TO_CLEAR: Entity-ID-based cache prefixes
 * - CACHE_GROUPS_TO_CLEAR: Cache groups to clear entirely
 */

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class BaseTable extends Table
{
    /** @var array<array{string, string}> Static cache entries to clear on save */
    protected const CACHES_TO_CLEAR = [];

    /** @var array<array{string, string}> Entity-ID cache prefixes to clear on save */
    protected const ID_CACHES_TO_CLEAR = [];

    /** @var array<string> Cache groups to clear entirely on save */
    protected const CACHE_GROUPS_TO_CLEAR = [];

    /**
     * After-save handler for automatic cache invalidation.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @param \Cake\Datasource\EntityInterface $entity The saved entity
     * @param \ArrayObject $options Save options
     * @return void
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
     * Add branch-based data scoping to a query.
     *
     * Child tables should override for custom branch relationships.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify
     * @param array<int> $branchIDs Authorized branch IDs
     * @return \Cake\ORM\Query\SelectQuery Query with branch filtering
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
