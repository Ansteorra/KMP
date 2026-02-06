<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use Cake\ORM\Table;

/**
 * ServicePrincipals Table Policy
 *
 * Manages access control for service principals table operations.
 */
class ServicePrincipalsTablePolicy extends BasePolicy
{
    /**
     * Check if user can view the service principals index.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Cake\ORM\Table $table The table
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, $table, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $table);
    }

    /**
     * Check if user can add service principals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \Cake\ORM\Table $table The table
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, $table, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $table);
    }
}
