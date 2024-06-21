<?php

declare(strict_types=1);

namespace Activities\Policy;

use Authorization\IdentityInterface;
use Activities\Model\Entity\Authorization;
use App\Policy\BasePolicy;

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

    public function canMemberAuthorizations(
        IdentityInterface $user,
        Authorization $authorization,
    ): bool {
        $cando = $this->_hasNamedPermission($user, "Can Manage Members");
        if ($cando) {
            return true;
        }
        return $authorization->member_id == $user->getIdentifier();
    }

    public function activityAuthorizations(
        IdentityInterface $user,
        Authorization $authorization,
    ): bool {
        $cando = $this->_hasNamedPermission($user, "Can Manage Activities");
        if ($cando) {
            return true;
        }
        return $authorization->member_id == $user->getIdentifier();
    }
}
