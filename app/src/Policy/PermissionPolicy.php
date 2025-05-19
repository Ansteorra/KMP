<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * role policy
 */
class PermissionPolicy extends BasePolicy
{
    /**
     * Check if user can update policy
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canUpdatePolicy(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view the Matrix
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canMatrix(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}