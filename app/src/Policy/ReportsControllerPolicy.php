<?php

namespace App\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;

class ReportsControllerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Core Reports";

    public function canRolesList(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canPermissionsWarrantsRoster(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}