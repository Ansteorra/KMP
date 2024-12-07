<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Warrants;
use Authorization\IdentityInterface;

/**
 * Warrants policy
 */
class WarrantsPolicy
{
    /**
     * Check if $user can add Warrants
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Warrants $warrants
     * @return bool
     */
    public function canAdd(IdentityInterface $user, Warrants $warrants)
    {
    }

    /**
     * Check if $user can edit Warrants
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Warrants $warrants
     * @return bool
     */
    public function canEdit(IdentityInterface $user, Warrants $warrants)
    {
    }

    /**
     * Check if $user can delete Warrants
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Warrants $warrants
     * @return bool
     */
    public function canDelete(IdentityInterface $user, Warrants $warrants)
    {
    }

    /**
     * Check if $user can view Warrants
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Warrants $warrants
     * @return bool
     */
    public function canView(IdentityInterface $user, Warrants $warrants)
    {
    }
}
