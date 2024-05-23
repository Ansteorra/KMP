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
    public function scopeIndex(IdentityInterface $user, $query)
    {
        if ($user->isSuperUser())
            return $query;
        else
            return $query->where(['approver_id' => $user->getIdentifier()]);
    }

    public function scopeMyQueue(IdentityInterface $user, $query)
    {
        return $query->where(['approver_id' => $user->getIdentifier()]);
    }
}
