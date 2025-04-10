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
    public function canRevoke(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAdd(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canRenew(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canMemberAuthorizations(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function activityAuthorizations(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}