<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\KMP\KmpIdentityInterface;
use Activities\Model\Entity\Authorization;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * ActivityGroups policy
 */
class AuthorizationPolicy extends BasePolicy
{
    /**
     * Check if the user can revoke authorization
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Activities\Model\Entity\Authorization $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canRevoke(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can add an authorization request
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Activities\Model\Entity\Authorization $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can request a renewal of an authorization
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Activities\Model\Entity\Authorization $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canRenew(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can view a specific users authorizations
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Activities\Model\Entity\Authorization $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canMemberAuthorizations(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can view a specific users authorizations
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Activities\Model\Entity\Authorization $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function activityAuthorizations(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}