<?php

namespace Officers\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

class RostersControllerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Officers";
    protected string $REQUIRED_ROSTER_PERMISSION = "Can Create Officer Roster";

    public function canAdd(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canCreateRoster(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
