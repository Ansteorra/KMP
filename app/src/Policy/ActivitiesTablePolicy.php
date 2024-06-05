<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\ActivitiesTable;
use Authorization\IdentityInterface;

class ActivitiesTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Activities";
}
