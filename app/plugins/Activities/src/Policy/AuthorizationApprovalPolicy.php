<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApproval;
use Activities\Model\Table\ActivitiesTable;
use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

class AuthorizationApprovalPolicy extends BasePolicy
{
    /**
     * Check if the user can approve authorizations
     * @param KmpIdentityInterface $user
     * @param AuthorizationApproval $entity
     * @param array ...$optionalArgs
     * @return bool
     */
    function canApprove(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
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

    /**
     * Check if the user can deny authorizations
     * @param KmpIdentityInterface $user
     * @param AuthorizationApproval $entity
     * @param array ...$optionalArgs
     * @return bool
     */
    function canDeny(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
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



    /**
     * Check if the user can view authorizations
     * @param KmpIdentityInterface $user
     * @param AuthorizationApproval $entity
     * @param array ...$optionalArgs
     * @return bool
     */
    function canView(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $entity->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can view their own authorization queue
     * @param KmpIdentityInterface $user
     * @param AuthorizationApproval $entity
     * @param array ...$optionalArgs
     * @return bool
     */
    public function canMyQueue(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    /**
     * Check if the user can view a list of others who can approve an authorization.
     * @param KmpIdentityInterface $user
     * @param AuthorizationApproval $entity
     * @param array ...$optionalArgs
     * @return bool
     */
    function canAvailableApproversList(KmpIdentityInterface $user, $approval): bool
    {
        $member_id = $user->getIdentifier();
        if ($member_id === $approval->approver_id) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $approval);
    }
}
