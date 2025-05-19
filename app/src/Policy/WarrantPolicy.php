<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;

/**
 * Warrant policy
 */
class WarrantPolicy extends BasePolicy
{
    /**
     * Check if user can access allWarrants
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAllWarrants(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can deactivate
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDeactivate(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can decline warrant in roster
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDeclineWarrantInRoster(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
