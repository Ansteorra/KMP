<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;

/**
 * Departments Table Authorization Policy
 *
 * Provides table-level authorization for bulk operations, query scoping,
 * and administrative data access for departments.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class DepartmentsTablePolicy extends BasePolicy
{
    /**
     * Scope gridData using same authorization as index action.
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
