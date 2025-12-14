<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;

/**
 * Authorization policy for Event entities in the Awards plugin.
 *
 * Manages access control for award events including ceremony coordination and temporal management.
 * Inherits standard CRUD authorization from BasePolicy.
 *
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see \Awards\Model\Entity\Event Event entity
 * @see /docs/5.2.9-awards-event-policy.md Full documentation
 */
class EventPolicy extends BasePolicy
{
    /**
     * Authorize access to comprehensive event listing across all temporal boundaries.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting access
     * @param mixed $entity The Events table or related entity
     * @param mixed ...$args Additional arguments for authorization context
     * @return bool True if user can access comprehensive event listing
     */
    public function canAllEvents(KmpIdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
