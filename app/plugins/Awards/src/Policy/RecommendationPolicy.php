<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;

/**
 * DomainPolicy policy
 */
class RecommendationPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Recommendations";

    public function canBoard(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canAdd(IdentityInterface $user, $entity)
    {
        return true;
    }
}