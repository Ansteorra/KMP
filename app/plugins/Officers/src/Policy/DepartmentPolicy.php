<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;
use App\Model\Entity\BaseEntity;

/**
 * Department Authorization Policy
 *
 * Controls entity-level access for Department operations including viewing,
 * creation, modification, and administrative management.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class DepartmentPolicy extends BasePolicy
{
    /**
     * Check if user can view all departments.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity The department entity for context
     * @return bool True if authorized to view all departments
     */
    public function canSeeAllDepartments(
        KmpIdentityInterface $user,
        BaseEntity $entity,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
