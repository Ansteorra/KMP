<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;

/**
 * DomainsTablePolicy policy
 */
class RecommendationsTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Recommendations";

    public function canAdd(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        return true;
    }
}
