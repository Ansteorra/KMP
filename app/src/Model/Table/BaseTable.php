<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use App\Model\Entity\AppSetting;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Datasource\EntityInterface;
use Cake\Cache\Cache;
use Cake\Log\Log;

class BaseTable extends Table
{
    protected const CACHES_TO_CLEAR = [];
    protected const ID_CACHES_TO_CLEAR = [];
    protected const CACHE_GROUPS_TO_CLEAR = [];

    public function afterSave($event, $entity, $options)
    {
        foreach ($this::CACHES_TO_CLEAR as $cache) {
            Cache::delete($cache[0], $cache[1]);
        }
        foreach ($this::ID_CACHES_TO_CLEAR as $cache) {
            Cache::delete($cache . $entity->id, $cache[1]);
        }
        foreach ($this::CACHE_GROUPS_TO_CLEAR as $cache) {
            Cache::clearGroup($cache);
        }
    }

    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }
        $query = $query->where([
            "Branches.id IN" => $branchIDs,
        ]);
        return $query;
    }
}