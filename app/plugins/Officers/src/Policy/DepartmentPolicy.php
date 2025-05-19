<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;
use App\Model\Entity\BaseEntity;

/**
 * Department policy
 */
class DepartmentPolicy extends BasePolicy
{


    /**
     * Check if the user can view all departments
     * @param KmpIdentityInterface $user The user
     * @param BaseEntity $entity The entity
     * @return bool
     */
    public function canSeeAllDepartments(
        KmpIdentityInterface $user,
        BaseEntity $entity,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
