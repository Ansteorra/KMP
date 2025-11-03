<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\GatheringStaff;
use Authorization\IdentityInterface;

/**
 * GatheringStaff policy
 *
 * Authorization policy for managing gathering staff. Staff management is controlled
 * through the parent gathering's authorization.
 */
class GatheringStaffPolicy
{
    /**
     * Check if user can add staff to a gathering
     *
     * User must have edit permissions on the gathering
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\GatheringStaff $staff The staff entity.
     * @return bool
     */
    public function canAdd(IdentityInterface $user, GatheringStaff $staff): bool
    {
        // This is handled via the gathering's edit permission in the controller
        return true;
    }

    /**
     * Check if user can edit a staff member
     *
     * User must have edit permissions on the gathering
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\GatheringStaff $staff The staff entity.
     * @return bool
     */
    public function canEdit(IdentityInterface $user, GatheringStaff $staff): bool
    {
        // This is handled via the gathering's edit permission in the controller
        return true;
    }

    /**
     * Check if user can delete a staff member
     *
     * User must have edit permissions on the gathering
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\GatheringStaff $staff The staff entity.
     * @return bool
     */
    public function canDelete(IdentityInterface $user, GatheringStaff $staff): bool
    {
        // This is handled via the gathering's edit permission in the controller
        return true;
    }
}
