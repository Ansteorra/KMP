<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Tables\WarrantsTable;
use Authorization\IdentityInterface;

class WarrantRostersTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Warrants";
    protected string $REQUIRED_VIEW_PERMISSION = "Can View Warrants";

    public function canView(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canIndex(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAllRosters(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function scopeAllRosters(IdentityInterface $user, $query)
    {
        return parent::scopeIndex($user, $query);
    }
}
