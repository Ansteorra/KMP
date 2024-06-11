<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Table\MemberRoles;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class MembersTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Members";

    public function scopeVerifyQueue(IdentityInterface $user, $query)
    {
        return $query;
    }
    function canVerifyQueue(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}