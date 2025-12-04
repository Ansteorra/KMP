<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;
use App\KMP\KmpIdentityInterface;

/**
 * Table-level authorization policy for Awards data operations.
 * 
 * Provides query scoping based on user branch permissions and recommendation approval authority.
 * Filters awards to show only those at levels the user can approve.
 * 
 * See /docs/5.2.6-awards-table-policy.md for complete documentation.
 *
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class AwardsTablePolicy extends BasePolicy
{
    /**
     * Apply query scoping based on user branch permissions and approval authority.
     * 
     * Filters awards by authorized branches and approval levels derived from
     * RecommendationPolicy canApproveLevel* methods.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user requesting access
     * @param \Cake\ORM\Query\SelectQuery $query The Awards table query to be scoped
     * @return \Cake\ORM\Query\SelectQuery Scoped query with branch and level filtering
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, "canIndex");
        if (empty($branchIds)) {
            return $query;
        }
        $branchPolicies = $user->getPolicies($branchIds);
        $approvaLevels = [];
        $recommendationPolicies = $branchPolicies["Awards\Policy\RecommendationPolicy"]
            ?? [];
        foreach ($recommendationPolicies as $method => $policy) {
            //if the method name starts with 'canApproveLevel' then lets get the level
            if (strpos($method, 'canApproveLevel') === 0) {
                $level = str_replace("canApproveLevel", "", $method);
                $approvaLevels[] = $level;
            }
        }
        $query = $table->addBranchScopeQuery($query, $branchIds);
        if (!empty($approvaLevels)) {
            return $query->contain(['Levels'])->where(['Levels.name in' => $approvaLevels]);
        }
        return $query;
    }

    /**
     * Check if user can access gridData scope (Dataverse grid data endpoint)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
