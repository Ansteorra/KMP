<?php
declare(strict_types=1);

namespace Waivers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use Cake\ORM\Table;

/**
 * GatheringActivityWaiver Entity Authorization Policy
 *
 * Provides entity-level authorization for gathering activity waiver operations.
 * Inherits standard CRUD operations from BasePolicy.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringActivityWaiverPolicy extends BasePolicy
{
    /**
     * Activity waiver requirements are global catalog data with no branch scope.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The entity or table.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_getBranchIdsForPolicy($user, __FUNCTION__) === null
            && $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Activity waiver requirements are global catalog data with no branch scope.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_getBranchIdsForPolicy($user, __FUNCTION__) === null
            && $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }

    /**
     * Get available waiver types.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function availableWaiverTypes(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_getBranchIdsForPolicy($user, $method) === null
            && $this->_hasPolicy($user, $method, $entity);
    }
}
