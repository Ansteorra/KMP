<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\Policy\BasePolicy;

class ActivitiesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Activities";
}