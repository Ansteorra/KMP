<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApproval;
use Authorization\IdentityInterface;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;

class AuthorizationApprovalPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Queues";

    function canApprove(IdentityInterface $user, $approval): bool
    {
        $authorization_id = $approval->authorization_id;
        $authorization = $approval->authorization;
        $activity_id = null;
        if ($authorization) {
            $activity_id = $authorization->activity_id;
        }
        if (!$activity_id) {
            $activity_id = TableRegistry::getTableLocator()
                ->get("Activities.Authorizations")
                ->get($authorization_id)->activity_id;
        }
        return $user->canAuthorizeType($activity_id) &&
            $user->getIdentifier() === $approval->approver_id;
    }

    function canView(IdentityInterface $user, $approval): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $approval->approver_id) {
            return true;
        }
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canMyQueue(IdentityInterface $user, $entity)
    {
        return $user->canHaveAuthorizationQueue();
    }

    function canAvailableApproversList(IdentityInterface $user, $approval): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $approval->approver_id) {
            return true;
        }
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}
