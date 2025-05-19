<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Authorization\Policy\ResultInterface;

class ReportsControllerPolicy extends BasePolicy
{
    /**
     * Check if user can access rolesList
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param Array $entity urlProps
     * @return \Authorization\Policy\ResultInterface|bool
     */
    public function canRolesList(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can access permissionsWarrantsRoster
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param Array $entity urlProps
     * @return \Authorization\Policy\ResultInterface|bool
     */
    public function canPermissionsWarrantsRoster(
        KmpIdentityInterface $user,
        array $urlProps,
    ): ResultInterface|bool {
        $method = __FUNCTION__;

        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}