<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * role policy
 */
class MembersTablePolicy extends BasePolicy
{
    /**
     * Check if user can access verifyQueue scope
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeVerifyQueue(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $query;
    }

    /**
     * Check if user can verify queue
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    function canVerifyQueue(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}