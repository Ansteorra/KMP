<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

class WarrantRostersTablePolicy extends BasePolicy
{
    /**
     * Check if user can access allRosters
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAllRosters(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can access allRosters scope
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeAllRosters(KmpIdentityInterface $user, mixed $query): mixed
    {
        return parent::scopeIndex($user, $query);
    }

    /**
     * Check if user can access gridData scope
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return parent::scopeIndex($user, $query);
    }
}
