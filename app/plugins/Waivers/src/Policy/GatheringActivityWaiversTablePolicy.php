<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use Cake\ORM\Table;

/**
 * GatheringActivityWaivers Table Authorization Policy
 *
 * Provides table-level authorization for GatheringActivityWaivers operations
 * including query scoping and bulk operations. Inherits standard authorization
 * methods from BasePolicy.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringActivityWaiversTablePolicy extends BasePolicy
{
    /**
     * Check if the user can retrieve available waiver types for an activity.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The entity or table.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canAvailableWaiverTypes(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity, ...$optionalArgs);
    }
}
