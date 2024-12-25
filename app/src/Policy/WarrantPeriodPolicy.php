<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\WarrantPeriod;
use Authorization\IdentityInterface;

/**
 * WarrantPeriod policy
 */
class WarrantPeriodPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
}