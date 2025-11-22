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
     * Check if user can access indexDv scope (Dataverse grid view)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeIndexDv(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }

    /**
     * Check if user can access gridData scope (Dataverse grid data endpoint)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }

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

    /**
     * Check if user can export member data to CSV
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
