<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\RolesTable;
use Authorization\IdentityInterface;

class RolesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = 'Can Manage Roles';
}