<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class BaseTable extends Table
{
    protected const CACHES_TO_CLEAR = [];
    protected const ID_CACHES_TO_CLEAR = [];
    protected const CACHE_GROUPS_TO_CLEAR = [];

    public function afterSave($event, $entity, $options): void
    {
        if (!empty($this::CACHES_TO_CLEAR)) {
            foreach ($this::CACHES_TO_CLEAR as $cache) {
                Cache::delete($cache[0], $cache[1]);
            }
        }
        if (!empty($this::ID_CACHES_TO_CLEAR)) {
            foreach ($this::ID_CACHES_TO_CLEAR as $cache) {
                Cache::delete($cache[0] . $entity->id, $cache[1]);
            }
        }
        if (!empty($this::CACHE_GROUPS_TO_CLEAR)) {
            foreach ($this::CACHE_GROUPS_TO_CLEAR as $cache) {
                Cache::clearGroup($cache);
            }
        }
    }

    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }
        $query = $query->where([
            'Branches.id IN' => $branchIDs,
        ]);

        return $query;
    }
}
