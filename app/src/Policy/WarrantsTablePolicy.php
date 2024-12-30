<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\WarrantsTable;
use Authorization\IdentityInterface;

class WarrantsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";

    public function canDeclineWarrantInRoster(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canDeactivate(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
}