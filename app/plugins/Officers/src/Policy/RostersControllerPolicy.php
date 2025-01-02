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
}