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
    protected string $REQUIRED_VIEW_PERMISSION = "Can View Members";

    public function scopeIndex(IdentityInterface $user, $query)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return $query;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_VIEW_PERMISSION)) {
            return $query;
        }
        return $query->where(["Members.id" => -1]);
    }

    public function canIndex(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        }
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_VIEW_PERMISSION);
        if ($canDo) {
            return true;
        }
        return false;
    }

    public function scopeVerifyQueue(IdentityInterface $user, $query)
    {
        return $query;
    }
    function canVerifyQueue(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}