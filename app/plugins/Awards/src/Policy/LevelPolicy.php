<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * DomainPolicy policy
 */
class LevelPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Awards";
}