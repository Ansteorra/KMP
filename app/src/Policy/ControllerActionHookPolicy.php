<?php

namespace App\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;

class ControllerActionHookPolicy
{
    public function __call(string $name, array $arguments)
    {
        /** @var ?\Authorization\Identity $user */
        return true;
    }
}
