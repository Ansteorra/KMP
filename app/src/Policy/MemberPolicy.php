<?php

declare(strict_types=1);

namespace App\Policy;

use App\Model\Table\MemberRoles;
use Authorization\IdentityInterface;

/**
 * role policy
 */
class MemberPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Members";

    public function canView(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }

    public function canPartialEdit(IdentityInterface $user, $entity)
    {
        return $entity->id == $user->getIdentifier();
    }

    public function canViewCard(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }
    public function canSendMobileCardEmail(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }

    public function canAddNote(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }
    public function canViewPrivateNotes(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        }
        return false;
    }

    function canRequestAuthorization(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }

    function canChangePassword(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }

    function canViewCardJson(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }

    function canDelete(IdentityInterface $user, $entity)
    {
        //only super users can delete and they should never get hear because of the before policy check.
        return false;
    }
    function canImportExpirationDates(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
    function canVerifyMembership(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
    function canVerifyQueue(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }
    function canEditAdditionalInfo(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        } else {
            return $entity->id == $user->getIdentifier();
        }
    }
}
