<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\Model\Entity\ActivityGroupsTable;
use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * ActivityGroupsTable policy
 */
class ActivityGroupsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Activities";
}