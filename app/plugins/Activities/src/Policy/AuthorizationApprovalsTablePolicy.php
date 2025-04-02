<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApprovalsTable;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;
use Activities\Model\Table\ActivitiesTable;

/**
 * AuthorizationApprovalsTable policy
 */
class AuthorizationApprovalsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Queues";

    public function canMyQueue(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    public function scopeIndex(IdentityInterface $user, $query)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return $query;
        } else {
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }
    public function scopeMyQueue(IdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }
    public function scopeView(IdentityInterface $user, $query)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return $query;
        } else {
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }
}
