<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Gathering Policy
 *
 * Manages authorization for gathering operations.
 * Authorization is driven by the Roles → Permissions → Policies system.
 */
class GatheringsTablePolicy extends BasePolicy
{
    /**
     * Check if user can index.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can calendar.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canCalendar(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can calendar grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canCalendarGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canCalendar($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can mobile calendar.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canMobileCalendar(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can mobile calendar data.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canMobileCalendarData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canMobileCalendar($user, $entity, ...$optionalArgs);
    }
}
