<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;

/**
 * WarrantRosters policy
 */
class WarrantRosterPolicy extends BasePolicy
{
    /**
     * Check if user can access allRosters
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAllRosters(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can approve
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canApprove(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can decline
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDecline(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
