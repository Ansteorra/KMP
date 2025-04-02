<?php

namespace App\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;

class ReportsControllerPolicy extends BasePolicy
{
    public function canRolesList(
        IdentityInterface $user,
        $entity,
    ): ResultInterface|bool {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canPermissionsWarrantsRoster(
        IdentityInterface $user,
        $entity,
    ): ResultInterface|bool {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}