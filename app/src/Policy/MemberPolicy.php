<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * role policy
 */
class MemberPolicy extends BasePolicy
{
    /**
     * Check if $user can view Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }

        return parent::canView($user, $entity);
    }

    /**
     * Check if $user can partial edit Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canPartialEdit(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }

        return false;
    }

    /**
     * Check if $user can view card
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewCard(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can send mobile card email
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canSendMobileCardEmail(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can add note
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAddNote(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can change password
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canChangePassword(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view card json
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewCardJson(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can delete Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        //only super users can delete and they should never get hear because of the before policy check.
        return false;
    }

    /**
     * Check if $user can import expiration dates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canImportExpirationDates(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can verify membership
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    function canVerifyMembership(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can verify queue
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    function canVerifyQueue(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can edit additional info
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    function canEditAdditionalInfo(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}