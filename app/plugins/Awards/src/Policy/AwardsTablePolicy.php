<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;
use App\KMP\KmpIdentityInterface;

/**
 * DomainsTablePolicy policy
 */
class AwardsTablePolicy extends BasePolicy
{
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
}
