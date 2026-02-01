<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Branch Entity Policy
 *
 * Allows all authenticated members to view branches and their public information
 * (officers, sub-branches, gatherings). Editing requires specific permissions.
 */
class BranchPolicy extends BasePolicy
{
    /**
     * All authenticated members can access the branches index.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The branch entity.
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can view any branch.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The branch entity.
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // All authenticated members can view branches
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can view branch officers.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The branch entity.
     * @return bool
     */
    public function canViewOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can view child branches.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The branch entity.
     * @return bool
     */
    public function canViewBranches(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * All authenticated members can view branch gatherings.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The branch entity.
     * @return bool
     */
    public function canViewGatherings(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }
}
