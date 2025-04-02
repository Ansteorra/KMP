<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Warrant;
use Authorization\IdentityInterface;

/**
 * Warrant policy
 */
class WarrantPolicy extends BasePolicy
{
    public function canAllWarrants(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canDeactivate(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canDeclineWarrantInRoster(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
