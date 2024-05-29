<?php

declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;
use App\Model\Entity\Authorization;

/**
 * AuthorizationGroups policy
 */
class AuthorizationPolicy extends BasePolicy
{
    public function canRevoke(
        IdentityInterface $user,
        Authorization $authorization,
    ): bool {
        return $this->_hasNamedPermission($user, "Can Revoke Authorizations");
    }
}
