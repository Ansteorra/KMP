<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\DepartmentsTable;
use Authorization\IdentityInterface;

/**
 * DepartmentsTable policy
 */
class DepartmentsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Departments";
}