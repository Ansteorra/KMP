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

    public function canAdd(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_ROSTER_PERMISSION)) {
            return true;
        }
        return false;
    }

    public function canCreateRoster(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_ROSTER_PERMISSION)) {
            return true;
        }
        return false;
    }
}