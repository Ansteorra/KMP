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

    public function canRelease(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}
