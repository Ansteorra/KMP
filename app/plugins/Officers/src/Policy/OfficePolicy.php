<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Permission;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;
use Cake\ORM\Table;


/**
 * Office Authorization Policy
 *
 * Controls entity-level access for Office operations including hierarchical
 * management, warrant requirements, and assignment authorization.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficePolicy extends BasePolicy
{
    /**
     * Check if $user can add RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function syncOfficers(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity, ...$optionalArgs);
    }
}