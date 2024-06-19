<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\NotesTable;
use Authorization\IdentityInterface;

/**
 * NotesTable policy
 */
class NotesTablePolicy
{
    /**
     * Check if $user can add NotesTable
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\NotesTable $notesTable
     * @return bool
     */
    public function canAdd(IdentityInterface $user, NotesTable $notesTable)
    {
    }

    /**
     * Check if $user can edit NotesTable
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\NotesTable $notesTable
     * @return bool
     */
    public function canEdit(IdentityInterface $user, NotesTable $notesTable)
    {
    }

    /**
     * Check if $user can delete NotesTable
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\NotesTable $notesTable
     * @return bool
     */
    public function canDelete(IdentityInterface $user, NotesTable $notesTable)
    {
    }

    /**
     * Check if $user can view NotesTable
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\NotesTable $notesTable
     * @return bool
     */
    public function canView(IdentityInterface $user, NotesTable $notesTable)
    {
    }
}
