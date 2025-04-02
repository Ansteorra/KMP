<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\RolesTable;
use Authorization\IdentityInterface;

class RolesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Roles";

    public function canDeletePermission(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canAddPermission(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canSearchMembers(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}
