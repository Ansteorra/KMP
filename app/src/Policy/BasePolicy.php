<?php

namespace App\Policy;

use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Model\Entity\Role;

use Authorization\IdentityInterface;
use Authorization\Policy\ResultInterface;
use Authorization\Policy\BeforePolicyInterface;


use App\Model\Entity\BaseEntity;


use Cake\Log\Log;

class BasePolicy implements BeforePolicyInterface
{

    protected string $REQUIRED_PERMISSION = "OVERRIDE_ME";
    public function before(
        ?IdentityInterface $user,
        mixed $resource,
        string $action,
    ): ResultInterface|bool|null {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        return null;
    }

    /**
     * Check if $user can add RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canAdd(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can edit RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canEdit(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can delete RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canDelete(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canView(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view role
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canIndex(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function scopeIndex(IdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, "canIndex");
        if (empty($branchIds)) {
            return $query;
        }
        return $table->addBranchScopeQuery($query, $branchIds);
    }


    public function canViewPrivateNotes(IdentityInterface $user, $entity)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    protected function _hasPolicy($user, string $policyMethod, $entity, ?int $branchId = null, $grantSource = null): bool
    {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        $policyClass = get_called_class();
        $policies = $this->_getPolicies($user);
        if (empty($policies)) {
            return false;
        }
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            return false;
        }
        $policyMethodData = $policyClassData[$policyMethod] ?? null;
        if (empty($policyMethodData)) {
            return false;
        }
        //check if we have a grant source to check
        if ($grantSource != null && $policyMethodData->entity_type != "Direct Grant") {
            if (
                $grantSource->entity_type != $policyMethodData->entity_type
                || $grantSource->entity_id != $policyMethodData->entity_id
            ) {
                return false;
            }
        }
        if ($policyMethodData->scoping_rule == Permission::SCOPE_GLOBAL) {
            return true;
        }

        //we check and filter by branch id if the call is not for a table
        if ($entity instanceof BaseEntity || $branchId != null) {
            if ($branchId == null) {
                $branchId = $entity->getBranchId();
            }
            if (empty($branchId)) {
                return true;
            }
            if (in_array($branchId, $policyMethodData->branch_ids)) {
                return true;
            }
            return false;
        } else {
            //if the entity is not a base entity, we assume it is a table and we return true
            return true;
        }
    }

    protected function _getBranchIdsForPolicy($user, string $policyMethod): array|null
    {
        if ($this->_isSuperUser($user)) {
            return null;
        }
        $policies = $this->_getPolicies($user);
        if (empty($policies)) {
            return [-10000000];
        }
        $policyClass = get_called_class();
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            return [-10000000];
        }
        $policyMethodData = $policyClassData[$policyMethod] ?? null;
        if (empty($policyMethodData)) {
            return [-10000000];
        }
        if ($policyMethodData->scoping_rule == Permission::SCOPE_GLOBAL) {
            return null;
        }
        return $policyMethodData->branch_ids;
    }

    protected function _getPolicies($user): array|null
    {
        $policies = $user->getPolicies();
        if (empty($policies)) {
            return null;
        }
        return $policies;
    }

    protected function _getPermissions($user): array|null
    {
        return $user->getPermissions();
    }
    protected function _isSuperUser($user): bool
    {
        return $user->isSuperUser();
    }
}