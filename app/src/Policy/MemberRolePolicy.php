<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\MemberRole;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class MemberRolePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Permissions";

    /**
     * Check if $user can view role
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\role $role
     * @return bool
     */
    public function canDeactivate(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}