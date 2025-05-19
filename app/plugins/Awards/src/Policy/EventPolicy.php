<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;

/**
 * DomainPolicy policy
 */
class EventPolicy extends BasePolicy
{

    public function canAllEvents(KmpIdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
