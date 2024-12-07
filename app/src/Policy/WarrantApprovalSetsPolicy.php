<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\WarrantApprovalSets;
use Authorization\IdentityInterface;

/**
 * WarrantApprovalSets policy
 */
class WarrantApprovalSetsPolicy
{
    /**
     * Check if $user can add WarrantApprovalSets
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\WarrantApprovalSets $warrantApprovalSets
     * @return bool
     */
    public function canAdd(IdentityInterface $user, WarrantApprovalSets $warrantApprovalSets)
    {
    }

    /**
     * Check if $user can edit WarrantApprovalSets
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\WarrantApprovalSets $warrantApprovalSets
     * @return bool
     */
    public function canEdit(IdentityInterface $user, WarrantApprovalSets $warrantApprovalSets)
    {
    }

    /**
     * Check if $user can delete WarrantApprovalSets
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\WarrantApprovalSets $warrantApprovalSets
     * @return bool
     */
    public function canDelete(IdentityInterface $user, WarrantApprovalSets $warrantApprovalSets)
    {
    }

    /**
     * Check if $user can view WarrantApprovalSets
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\WarrantApprovalSets $warrantApprovalSets
     * @return bool
     */
    public function canView(IdentityInterface $user, WarrantApprovalSets $warrantApprovalSets)
    {
    }
}
