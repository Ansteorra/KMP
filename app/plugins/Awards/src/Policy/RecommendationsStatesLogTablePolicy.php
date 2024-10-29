<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * DomainsTablePolicy policy
 */
class RecommendationsStatesLogTablePolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Recommendations";
}