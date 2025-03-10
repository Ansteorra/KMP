<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\BranchesTable;
use Authorization\IdentityInterface;

/**
 * ActivityGroupsTable policy
 */
class BranchesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Branches";
    protected string $REQUIRED_PERMISSION_VIEW = "Can View Branches";
    /**
     * Check if $user can view role
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\role $role
     * @return bool
     */
    public function canIndex(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_VIEW)) {
            return true;
        }
        return false;
    }
}