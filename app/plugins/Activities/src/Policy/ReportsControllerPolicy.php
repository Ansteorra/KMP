<?php

namespace Activities\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

class ReportsControllerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Activity Reports";

    public function canActivityWarrantsRoster(
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