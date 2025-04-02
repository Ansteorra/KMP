<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\WarrantRoster;
use Authorization\IdentityInterface;

/**
 * WarrantRosters policy
 */
class WarrantRosterPolicy extends BasePolicy
{

    public function canAllRosters(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canApprove(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canDecline(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
