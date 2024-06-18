<?php

namespace Officers\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

class ReportsControllerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Officer Reports";

    public function canDepartmentOfficersRoster(
        IdentityInterface $user,
        mixed $resource,
    ): ResultInterface|bool {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}
