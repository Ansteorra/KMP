<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\WarrantApprovalSet;
use Authorization\IdentityInterface;

/**
 * WarrantApprovalSets policy
 */
class WarrantApprovalSetPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";

    public function canDeletePermission(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canAddPermission(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}