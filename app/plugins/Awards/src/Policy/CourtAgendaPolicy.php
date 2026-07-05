<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;

/**
 * Authorization policy for Awards court agendas.
 */
class CourtAgendaPolicy extends BasePolicy
{
    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canGathering(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canPrintAgenda(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canImport(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddSegment(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddBlock(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canAddBestowal(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canMoveToRoaming(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canUpdateItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canMoveItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user User.
     * @param \App\Model\Entity\BaseEntity $entity Agenda entity.
     * @param mixed ...$optionalArgs Context.
     * @return bool
     */
    public function canRemoveItem(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }
}
