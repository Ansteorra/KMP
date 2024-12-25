<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\WarrantPeriodTable;
use Authorization\IdentityInterface;

/**
 * WarrantPeriodTable policy
 */
class WarrantPeriodsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
}