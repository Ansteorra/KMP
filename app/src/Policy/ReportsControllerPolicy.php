<?php

namespace App\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;

class ReportsControllerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Reports";

    public function canRolesList(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canWarrantsRoster(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canAuthorizations(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}
