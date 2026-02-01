<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * GatheringAttendances Table Policy
 *
 * Manages authorization for gathering attendance table operations.
 */
class GatheringAttendancesTablePolicy extends BasePolicy
{
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    public function canMyRsvps(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can view their own RSVPs
        return true;
    }

    public function canMobileRsvp(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can RSVP
        return true;
    }

    public function canMobileUnrsvp(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can cancel their own RSVP
        return true;
    }
}
