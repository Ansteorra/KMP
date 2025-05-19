<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;

/**
 * role policy
 */
class RolePolicy extends BasePolicy
{
    /**
     * Check if $user can add permissions to a role
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */

    public function canDeletePermission(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can remove permissions from a role
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canAddPermission(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity, ...$optionalArgs);
    }
}
