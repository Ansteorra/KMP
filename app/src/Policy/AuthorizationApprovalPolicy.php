<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationApproval;
use Authorization\IdentityInterface;
use Cake\ORM\TableRegistry;

class AuthorizationApprovalPolicy extends BasePolicy
{
    function canApprove(IdentityInterface $user,  $approval): bool
    {
        $authorization_id = $approval->get('authorization_id');
        $authorization_type_id = TableRegistry::getTableLocator()->get('Authorizations')->get($authorization_id)->authorization_type_id;
        return $user->canAuthorizeType($authorization_type_id) && $user->$user->getIdentifier() === $approval->approver_id;
    }

    function canView(IdentityInterface $user,  $approval): bool
    {
        $member_id = $user->$user->getIdentifier();
        if($member_id === $approval->approver_id){
            return true;
        }
        return false;
    }

    function canIndex(IdentityInterface $user, $query ): bool
    {
        $this->_hasAuthenticationsPermissions($user, $query);
    }

    public function canMyQueue(IdentityInterface $user, $entity)
    {
        $this->_hasAuthenticationsPermissions($user, $entity);

    }
}