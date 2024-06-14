<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\Model\Entity\ActivityGroups;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * ActivityGroups policy
 */
class ActivityGroupPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Activities";
}