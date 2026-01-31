<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * BranchesTable Policy
 *
 * Allows all authenticated members to list and view branches.
 * Editing and other operations require specific permissions.
 */
class BranchesTablePolicy extends BasePolicy
{
    /**
     * All authenticated members can view the branches index.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can access grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can view branches.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * No branch scoping for index - all authenticated members see all branches.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \Cake\ORM\Query\SelectQuery $query The query.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        // No branch scoping - all members can see all branches
        return $query;
    }
}