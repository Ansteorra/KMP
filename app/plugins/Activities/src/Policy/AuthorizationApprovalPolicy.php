<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApproval;
use Activities\Model\Table\ActivitiesTable;
use Authorization\IdentityInterface;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;

class AuthorizationApprovalPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Queues";

    function canApprove(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        $authorization_id = $entity->authorization_id;
        $authorization = $entity->authorization;
        $activity_id = null;
        if ($authorization) {
            $activity_id = $authorization->activity_id;
        }
        if (!$activity_id) {
            $activity_id = TableRegistry::getTableLocator()
                ->get("Activities.Authorizations")
                ->get($authorization_id)->activity_id;
        }
        return ActivitiesTable::canAuthorizeActivity($user, $activity_id);
    }

    function canDeny(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        $authorization_id = $entity->authorization_id;
        $authorization = $entity->authorization;
        $activity_id = null;
        if ($authorization) {
            $activity_id = $authorization->activity_id;
        }
        if (!$activity_id) {
            $activity_id = TableRegistry::getTableLocator()
                ->get("Activities.Authorizations")
                ->get($authorization_id)->activity_id;
        }
        return ActivitiesTable::canAuthorizeActivity($user, $activity_id);
    }




    function canView(IdentityInterface $user, $entity, ...$optionalArgs): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $entity->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canMyQueue(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    function canAvailableApproversList(IdentityInterface $user, $approval): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $approval->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $approval);
    }
}