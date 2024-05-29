<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationApprovalsTable;
use Authorization\IdentityInterface;

/**
 * AuthorizationApprovalsTable policy
 */
class AuthorizationApprovalsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Queues";

    public function canMyQueue(IdentityInterface $user, $entity)
    {
        return $user->canHaveAuthorizationQueue();
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
