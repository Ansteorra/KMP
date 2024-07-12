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
        return true;
    }

    public function canAdd(IdentityInterface $user, $entity)
    {
        return true;
    }

    public function canEdit(IdentityInterface $user, $entity)
    {
        return true;
    }
}