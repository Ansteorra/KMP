<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationGroups;
use Authorization\IdentityInterface;

/**
 * AuthorizationGroups policy
 */
class AuthorizationGroupPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Authorization Types";
}
