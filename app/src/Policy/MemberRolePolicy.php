<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;

/**
 * role policy
 */
class MemberRolePolicy extends BasePolicy
{
    /**
     * Check if $user can deactivate MemberRole
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDeactivate(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
