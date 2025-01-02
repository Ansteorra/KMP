<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * DepartmentsTable policy
 */
class OfficersTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Officers";
    protected string $REQUIRED_ASSIGN_PERMISSION = "Can Assign Officers";
    protected string $REQUIRED_RELEASE_PERMISSION = "Can Release Officers";

    public function canAdd(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_ASSIGN_PERMISSION)) {
            return true;
        }
        return false;
    }

    public function canRelease(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_RELEASE_PERMISSION)) {
            return true;
        }
        return false;
    }
}