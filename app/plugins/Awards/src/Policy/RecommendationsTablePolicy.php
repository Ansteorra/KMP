<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * DomainsTablePolicy policy
 */
class RecommendationsTablePolicy extends BasePolicy
{

    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, "canIndex");
        //if (empty($branchIds)) {
        //    return $query;
        //}
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
        if (!empty($branchIds)) {
            if ($branchIds[0] != -10000000) {
                $query = $table->addBranchScopeQuery($query, $branchIds);
            }
        }
        if (!empty($approvaLevels)) {
            return $query->contain(['Awards.Levels'])->where(['Levels.name in' => $approvaLevels]);
        }
        return $query;
    }

    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }
}