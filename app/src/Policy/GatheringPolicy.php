<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * Gathering Policy
 *
 * Manages authorization for gathering operations.
 * Authorization is driven by the Roles → Permissions → Policies system.
 */
class GatheringPolicy extends BasePolicy
{
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can view attendance information for a gathering
     *
     * Users with appropriate permissions can view attendance details including
     * total count and list of attendees who have shared with the hosting group.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewAttendance(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}