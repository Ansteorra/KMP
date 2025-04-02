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
    protected string $REQUIRED_VIEW_PERMISSION = "Can View Members";



    public function canView(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        return parent::canView($user, $entity);
    }

    public function canPartialEdit(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
    }

    public function canViewCard(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    public function canSendMobileCardEmail(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAddNote(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    function canChangePassword(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    function canViewCardJson(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    function canDelete(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        //only super users can delete and they should never get hear because of the before policy check.
        return false;
    }
    function canImportExpirationDates(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    function canVerifyMembership(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    function canVerifyQueue(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    function canEditAdditionalInfo(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        if ($entity->id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
