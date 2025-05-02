<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;
use Authorization\Policy\ResultInterface;

/**
 * Department policy
 */
class DepartmentPolicy extends BasePolicy
{

    public function canSeeAllDepartments(
        IdentityInterface $user,
        $entity,
    ): ResultInterface|bool {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
