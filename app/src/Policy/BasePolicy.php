<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Permission;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;
use Cake\Log\Log;
use Cake\ORM\Table;
use ReflectionMethod;

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
                $ref = new ReflectionMethod($this, $method);
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
     * @param \App\Model\Entity\BaseEntity|\App\Policy\Cake\ORM\Table $entity
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

    /**
     * Apply scope for index action.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param mixed $query
     */
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
    protected function _hasPolicy(
        KmpIdentityInterface $user,
        string $policyMethod,
        BaseEntity|Table $entity,
        ?int $branchId = null,
        $grantSource = null,
    ): bool {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        $policyClass = static::class;
        $policies = $this->_getPolicies($user);
        if (empty($policies)) {
            Log::write('debug', 'No policies found for user: ' . $user->getIdentifier());

            return false;
        }
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            Log::write('debug', 'No policies found for class: ' . $policyClass);

            return false;
        }
        $policyMethodData = $policyClassData[$policyMethod] ?? null;
        if (empty($policyMethodData)) {
            Log::write('debug', 'No policies found for method: ' . $policyClass . '-' . $policyMethod);

            return false;
        }
        if ($entity instanceof BaseEntity && $branchId == null) {
            $branchId = $entity->getBranchId();
        }
        if ($grantSource != null) {
            if (!$this->_matchesGrantSource($policyMethodData, $grantSource, $branchId)) {
                Log::write('debug', 'Grant source does not match policy method data');
                Log::write('debug', 'User: ' . $user->getIdentifier());
                Log::write('debug', 'Policy class: ' . $policyClass);
                Log::write('debug', 'Policy method: ' . $policyMethod);

                return false;
            }
        }
        if ($policyMethodData->scoping_rule == Permission::SCOPE_GLOBAL) {
            return true;
        }

        //we check and filter by branch id if the call is not for a table
        if ($entity instanceof BaseEntity || $branchId != null) {
            if (empty($branchId)) {
                return true;
            }
            if (in_array($branchId, $policyMethodData->branch_ids)) {
                return true;
            }
            Log::write('debug', 'Branch id does not match policy method data');

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
    protected function _hasPolicyForUrl(
        KmpIdentityInterface $user,
        string $policyMethod,
        array $urlProps,
        ?int $branchId = null,
        $grantSource = null,
    ): bool {
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
        if ($grantSource != null) {
            if (!$this->_matchesGrantSource($policyMethodData, $grantSource, $branchId)) {
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
     * Check whether a policy method was granted by a compatible source entity.
     *
     * @param object $policyMethodData Policy method authorization data
     * @param object $grantSource Source entity being checked
     * @param int|null $branchId Branch context for the authorization check
     * @return bool
     */
    protected function _matchesGrantSource(object $policyMethodData, object $grantSource, ?int $branchId = null): bool
    {
        $sources = $policyMethodData->grant_sources ?? [
            (object)[
                'entity_type' => $policyMethodData->entity_type ?? null,
                'entity_id' => $policyMethodData->entity_id ?? null,
                'branch_ids' => $policyMethodData->branch_ids ?? null,
            ],
        ];

        foreach ($sources as $source) {
            if (($source->entity_type ?? null) === 'Direct Grant') {
                return true;
            }

            if (
                ($source->entity_type ?? null) == ($grantSource->entity_type ?? null)
                && ($source->entity_id ?? null) == ($grantSource->entity_id ?? null)
            ) {
                return $this->_grantSourceIncludesBranch($source, $branchId);
            }
        }

        return false;
    }

    /**
     * Check whether a matching source grants the requested branch.
     *
     * @param object $source Matching grant source
     * @param int|null $branchId Branch context for the authorization check
     * @return bool
     */
    protected function _grantSourceIncludesBranch(object $source, ?int $branchId): bool
    {
        if ($branchId === null || !property_exists($source, 'branch_ids') || $source->branch_ids === null) {
            return true;
        }

        return in_array($branchId, $source->branch_ids);
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
