<?php

declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;
use App\Model\Entity\Authorization;

/**
 * ActivityGroups policy
 */
class AuthorizationPolicy extends BasePolicy
{
    public function canRevoke(
        IdentityInterface $user,
        Authorization $authorization,
    ): bool {
        return $this->_hasNamedPermission($user, "Can Revoke Authorizations");
    }

    public function canAdd(
        IdentityInterface $user,
        $authorization,
    ): bool {
        $cando = $this->_hasNamedPermission($user, "Can Add Authorizations");
        if ($cando) {
            return true;
        }
        return $authorization->member_id == $user->getIdentifier();
    }
}
