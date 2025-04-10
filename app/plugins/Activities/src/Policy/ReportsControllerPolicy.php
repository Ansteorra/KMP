<?php

namespace Activities\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

class ReportsControllerPolicy extends BasePolicy
{
    public function canActivityWarrantsRoster(IdentityInterface $user, $entity, ...$optionalArgs): ResultInterface|bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAuthorizations(IdentityInterface $user, $entity, ...$optionalArgs): ResultInterface|bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}