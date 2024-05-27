<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationApproval;
use Authorization\IdentityInterface;
use Cake\ORM\TableRegistry;

class AuthorizationApprovalPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Authorization Queues';

    function canApprove(IdentityInterface $user,  $approval): bool
    {
        $authorization_id = $approval->authorization_id;
        $authorization = $approval->authorization;
        $authorization_type_id;
        if($authorization){
            $authorization_type_id = $authorization->authorization_type_id;
        }
        if(!$authorization_type_id){
            $authorization_type_id = TableRegistry::getTableLocator()->get('Authorizations')->get($authorization_id)->authorization_type_id;
        }
        return $user->canAuthorizeType($authorization_type_id) && $user->getIdentifier() === $approval->approver_id;
    }

    function canView(IdentityInterface $user,  $approval): bool
    {
        $member_id = $user->getIdentifier();
        if($member_id === $approval->approver_id){
            return true;
        }
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canMyQueue(IdentityInterface $user, $entity)
    {
        return $user->canHaveAuthorizationQueue();
    }
    
    function canAvailableApproversList(IdentityInterface $user,  $approval): bool
    {
        $member_id = $user->getIdentifier();
        if($member_id === $approval->approver_id){
            return true;
        }
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
    
}