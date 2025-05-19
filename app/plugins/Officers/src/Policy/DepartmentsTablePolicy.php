<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;

/**
 * DepartmentsTable policy
 */
class DepartmentsTablePolicy extends BasePolicy
{
    //public const SKIP_BASE = 'true';
}