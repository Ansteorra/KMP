<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Table\MemberRoles;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class MemberRolesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Roles";

    public function canDeactivate(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}