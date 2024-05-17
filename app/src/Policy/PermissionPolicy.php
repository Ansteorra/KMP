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
    protected string $REQUIRED_PERMISSION = 'Can Manage Permissions';
    
}