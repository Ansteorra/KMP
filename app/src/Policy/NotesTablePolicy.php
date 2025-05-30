<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * NotesTable policy
 */
class NotesTablePolicy
{
    /**
     * Check if $user can add NotesTable
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $notesTable
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity $notesTable): bool
    {
        return false;
    }

    /**
     * Check if $user can edit NotesTable
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $notesTable
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $notesTable): bool
    {
        return false;
    }

    /**
     * Check if $user can delete NotesTable
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $notesTable
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $notesTable): bool
    {
        return false;
    }

    /**
     * Check if $user can view NotesTable
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $notesTable
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $notesTable): bool
    {
        return false;
    }
}