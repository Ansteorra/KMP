<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Role;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class RolePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Roles';
    
}