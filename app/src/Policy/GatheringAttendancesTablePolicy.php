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
    /**
     * Check if user can index.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Table $entity
     * @return bool
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can my rsvps.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Table $entity
     * @return bool
     */
    public function canMyRsvps(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can view their own RSVPs
        return true;
    }

    /**
     * Check if user can mobile rsvp.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Table $entity
     * @return bool
     */
    public function canMobileRsvp(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can RSVP
        return true;
    }

    /**
     * Check if user can mobile unrsvp.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Table $entity
     * @return bool
     */
    public function canMobileUnrsvp(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can cancel their own RSVP
        return true;
    }

    /**
     * Check if user can mobile update rsvp.
     *
     * @param KmpIdentityInterface $user
     * @param BaseEntity|Table $entity
     * @return bool
     */
    public function canMobileUpdateRsvp(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Any authenticated user can update their own RSVP
        return true;
    }
}
