<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\ActivityGroupsTable;
use Authorization\IdentityInterface;

/**
 * ActivityGroupsTable policy
 */
class AppSettingsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Settings";
}