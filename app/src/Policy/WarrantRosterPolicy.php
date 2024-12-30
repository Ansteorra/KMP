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
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
    public function canApprove(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canDecline(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}