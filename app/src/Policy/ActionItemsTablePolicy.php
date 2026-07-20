<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * ActionItemsTable policy — table-level authorization for the to-do subsystem.
 *
 * Any authenticated user can access their own to-do queue; per-item eligibility
 * is enforced by ActionItemPolicy / ActionItemService. Admin-wide listings
 * require super user.
 */
class ActionItemsTablePolicy extends BasePolicy
{
    /**
     * Allow authenticated users to view their personal to-do queue.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Table
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canMyTasks(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to load their personal to-do grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Table
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canMyTasksGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to view their personal mobile to-do queue.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Table
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canMobileMyTasks(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canMyTasks($user, $entity, ...$optionalArgs);
    }

    /**
     * Allow authenticated users to load their personal mobile to-do data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity Table
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canMobileMyTasksData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canMyTasks($user, $entity, ...$optionalArgs);
    }
}
