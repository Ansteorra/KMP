<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Permission;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;
use Cake\ORM\Table;

class BasePolicy implements BeforePolicyInterface
{
    /**
     * Check if $user is a super user and can skip auth with an auto True
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param mixed $resource The resource.
     * @param string $action The action.
     * @return bool|null
     */
    public function before(
        ?IdentityInterface $user,
        mixed $resource,
        string $action,
    ): ResultInterface|bool|null {
        if ($this->_isSuperUser($user)) {
            return true;
        }

        // Handle URL-based authorization (array resources from authorizeCurrentUrl).
        // Only intercept when the target can* method is inherited from BasePolicy
        // (whose type hints don't accept arrays). Subclass overrides that accept
        // arrays are left to run normally.
        if (is_array($resource) && $user instanceof KmpIdentityInterface) {
            $method = 'can' . ucfirst($action);
            if (method_exists($this, $method)) {
                $ref = new \ReflectionMethod($this, $method);
                if ($ref->getDeclaringClass()->getName() === self::class) {
                    return $this->_hasPolicyForUrl($user, $method, $resource);
                }
            }
        }

        return null;
    }

    /**
     * Check if $user can add RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity, ...$optionalArgs);
    }

    /**
     * Check if $user can edit RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can delete RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|Cake\ORM\Table $entity
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view role
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }


    /**
     * Check if $user can view role
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }

    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, 'canIndex');
        if (empty($branchIds)) {
            return $query;
        }

        return $table->addBranchScopeQuery($query, $branchIds);
    }

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canViewPrivateNotes(KmpIdentityInterface $user, BaseEntity $entity): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
    protected function _hasPolicy(KmpIdentityInterface $user, string $policyMethod, BaseEntity|Table $entity, ?int $branchId = null, $grantSource = null): bool
    {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        $policyClass = static::class;
        $policies = $this->_getPolicies($user);
        if (empty($policies)) {
            \Cake\Log\Log::write('debug', 'No policies found for user: ' . $user->getIdentifier());
            return false;
        }
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            \Cake\Log\Log::write('debug', 'No policies found for class: ' . $policyClass);
            return false;
        }
        $policyMethodData = $policyClassData[$policyMethod] ?? null;
        if (empty($policyMethodData)) {
            \Cake\Log\Log::write('debug', 'No policies found for method: ' . $policyClass . "-" . $policyMethod);
            return false;
        }
        //check if we have a grant source to check
        if ($grantSource != null && $policyMethodData->entity_type != 'Direct Grant') {
            if (
                $grantSource->entity_type != $policyMethodData->entity_type
                || $grantSource->entity_id != $policyMethodData->entity_id
            ) {
                \Cake\Log\Log::write('debug', 'Grant source does not match policy method data');
                \Cake\Log\Log::write('debug', 'User: ' . $user->getIdentifier());
                \Cake\Log\Log::write('debug', 'Policy class: ' . $policyClass);
                \Cake\Log\Log::write('debug', 'Policy method: ' . $policyMethod);
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
            \Cake\Log\Log::write('debug', 'Branch id does not match policy method data');
            return false;
        } else {
            //if the entity is not a base entity, we assume it is a table and we return true
            return true;
        }
    }

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param Array|\Cake\ORM\Table $entity
     * @return bool
     */
    protected function _hasPolicyForUrl(KmpIdentityInterface $user, string $policyMethod, array $urlProps, ?int $branchId = null, $grantSource = null): bool
    {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        $policyClass = static::class;
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
        if ($grantSource != null && $policyMethodData->entity_type != 'Direct Grant') {
            if (
                $grantSource->entity_type != $policyMethodData->entity_type
                || $grantSource->entity_id != $policyMethodData->entity_id
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return ?array
     */
    protected function _getBranchIdsForPolicy(KmpIdentityInterface $user, string $policyMethod): ?array
    {
        if ($this->_isSuperUser($user)) {
            return null;
        }
        $policies = $this->_getPolicies($user);
        if (empty($policies)) {
            return [-10000000];
        }
        $policyClass = static::class;
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

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    protected function _getPolicies(KmpIdentityInterface $user): ?array
    {
        $policies = $user->getPolicies();
        if (empty($policies)) {
            return null;
        }

        return $policies;
    }

    /**
     * Check if $user can view hidden
     *
     * @param \App\KMP\KmpIdentityInterface  $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    protected function _getPermissions(KmpIdentityInterface $user): ?array
    {
        return $user->getPermissions();
    }

    /**
     * Check if $user is a super user
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @return bool
     */
    protected function _isSuperUser(KmpIdentityInterface $user): bool
    {
        return $user->isSuperUser();
    }
}