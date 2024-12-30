<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Warrant;
use Authorization\IdentityInterface;

/**
 * Warrant policy
 */
class WarrantPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
}