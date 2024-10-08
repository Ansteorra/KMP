<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\ActivityGroups;
use Authorization\IdentityInterface;

/**
 * ActivityGroups policy
 */
class AppSettingPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Settings";
}