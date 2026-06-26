<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use Cake\ORM\Table;

/**
 * Table-level authorization for court agenda listings.
 */
class CourtAgendasTablePolicy extends BasePolicy
{
    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param mixed $query Query.
     * @return mixed
     */
    public function scopeIndex(KmpIdentityInterface $user, $query): mixed
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, 'canIndex');

        if (!empty($branchIds) && $branchIds[0] != -10000000) {
            $query = $table->addBranchScopeQuery($query, $branchIds);
        }

        return $query;
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Entity or table.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }
}
