<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Permissions;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class PermissionPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Permissions";

    public function canUpdatePolicy(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
