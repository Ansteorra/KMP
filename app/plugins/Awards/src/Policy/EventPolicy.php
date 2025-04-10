<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;

/**
 * DomainPolicy policy
 */
class EventPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Awards";

    public function canAllEvents(IdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
