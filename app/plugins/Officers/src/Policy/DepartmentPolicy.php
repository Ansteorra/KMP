<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * Department policy
 */
class DepartmentPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Departments";
}