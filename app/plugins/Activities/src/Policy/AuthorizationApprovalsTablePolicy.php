<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\AuthorizationApprovalsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Activities\Model\Table\ActivitiesTable;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;

/**
 * AuthorizationApprovalsTable policy
 */
class AuthorizationApprovalsTablePolicy extends BasePolicy
{

    /**
     * Check if the user can view the index of their queue
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs
     * @return bool
     */
    public function canMyQueue(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs)
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    /**
     * Check if the user can view all queues
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs
     * @return bool
     */
    public function canAllQueues(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check Applies scope to what is in the index view
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @param mixed ...$optionalArgs
     * @return bool
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        //get the AuthorizationApprovalsTable   
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        // get an empty instance of the table
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();
        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        } else {
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }

    /**
     * Check Applies scope to what is in the my queue view
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @param mixed ...$optionalArgs
     * @return bool
     */
    public function scopeMyQueue(KmpIdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Check Applies scope to what is in the view view
     * @param KmpIdentityInterface $user
     * @param BaseEntity $entity
     * @param mixed ...$optionalArgs
     * @return bool
     */
    public function scopeView(KmpIdentityInterface $user, $query)
    {
        //get the AuthorizationApprovalsTable   
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        // get an empty instance of the table
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();
        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        } else {
            return $query->where(["approver_id" => $user->getIdentifier()]);
        }
    }
}