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


    public function scopeVerifyQueue(IdentityInterface $user, $query)
    {
        return $query;
    }
    function canVerifyQueue(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
