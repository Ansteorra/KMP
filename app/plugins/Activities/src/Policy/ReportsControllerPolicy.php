<?php

namespace Activities\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

class ReportsControllerPolicy extends BasePolicy
{
    /**
     * Check if the user can view the reports
     * @param KmpIdentityInterface $user The user
     * @param Array $entity The entity
     * @return bool
     */
    public function canActivityWarrantsRoster(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if the user can view the reports
     * @param KmpIdentityInterface $user The user
     * @param Array $entity The entity
     * @return bool
     */
    public function canAuthorizations(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}