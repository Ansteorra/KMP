<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Table\PermissionsTable;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class PermissionsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Permissions';
    
}