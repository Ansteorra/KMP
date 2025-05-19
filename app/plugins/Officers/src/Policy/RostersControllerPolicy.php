<?php

namespace Officers\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

class RostersControllerPolicy extends BasePolicy
{
    /**
     * Check if the user can view the reports
     * @param KmpIdentityInterface $user The user
     * @param BaseEntity $entity The entity
     * @return bool
     */
    public function canCreateRoster(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
