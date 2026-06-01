<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Table-level authorization policy for Bestowals in the Awards plugin.
 *
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base table authorization functionality
 */
class BestowalsTablePolicy extends BasePolicy
{
    /**
     * Apply authorization scoping to bestowal queries.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting data access
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with authorization filtering
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, 'canIndex');

        if (!empty($branchIds) && $branchIds[0] != -10000000) {
            $query = $table->addBranchScopeQuery($query, $branchIds);
        }

        return $query;
    }

    /**
     * Authorize bestowal export to CSV.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting export access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool True if user has index permission
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }
}
