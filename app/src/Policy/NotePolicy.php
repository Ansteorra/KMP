<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Note;
use Authorization\IdentityInterface;

/**
 * Note policy
 */
class NotePolicy
{
    /**
     * Check if $user can add Note
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Note $note
     * @return bool
     */
    public function canAdd(IdentityInterface $user, Note $note)
    {
        return $user->checkCan("addNote", $note->entity_type);
    }

    /**
     * Check if $user can edit Note
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Note $note
     * @return bool
     */
    public function canEdit(IdentityInterface $user, Note $note) {}

    /**
     * Check if $user can delete Note
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Note $note
     * @return bool
     */
    public function canDelete(IdentityInterface $user, Note $note) {}

    /**
     * Check if $user can view Note
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Note $note
     * @return bool
     */
    public function canView(IdentityInterface $user, Note $note) {}
}
