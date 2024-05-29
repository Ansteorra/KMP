<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\AuthorizationTypesTable;
use Authorization\IdentityInterface;

class AuthorizationTypesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Types";
}
