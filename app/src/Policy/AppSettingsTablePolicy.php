<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\AuthorizationGroupsTable;
use Authorization\IdentityInterface;

/**
 * AuthorizationGroupsTable policy
 */
class AppSettingsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage App Settings";
}
