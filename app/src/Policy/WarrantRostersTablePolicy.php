<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\WarrantsTable;
use Authorization\IdentityInterface;

class WarrantRostersTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
}
