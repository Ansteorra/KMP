<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Permissions Table Policy
 * 
 * Provides authorization for permission management actions including
 * the policy matrix interface and export/import functionality.
 */
class PermissionsTablePolicy extends BasePolicy
{
    /**
     * Check if user can access matrix
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

    /**
     * Check if user can export permission policies
     * 
     * Only super users can export permission policies for security purposes.
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canExportPolicies(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Check if user can import permission policies
     * 
     * Only super users can import permission policies for security purposes.
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canImportPolicies(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Check if user can preview import
     * 
     * Only super users can preview import for security purposes.
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity Entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canPreviewImport(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }
}
