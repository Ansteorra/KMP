<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * WorkflowInstancesTable policy — restricts instance monitoring to super users.
 */
class WorkflowInstancesTablePolicy extends BasePolicy
{
    public function canInstances(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    public function canGridData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    public function canViewInstance(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }
}
