<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * DepartmentsTable policy
 */
class DepartmentsTablePolicy extends BasePolicy {
    public const SKIP_BASE = 'true';

}