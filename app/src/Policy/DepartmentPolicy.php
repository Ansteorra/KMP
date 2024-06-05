<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Department;
use Authorization\IdentityInterface;

/**
 * Department policy
 */
class DepartmentPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Departments";
}