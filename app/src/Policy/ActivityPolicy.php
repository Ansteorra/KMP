<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Activity;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class ActivityPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Activities";
}
