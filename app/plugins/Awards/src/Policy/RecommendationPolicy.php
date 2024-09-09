<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;

/**
 * DomainPolicy policy
 */
class RecommendationPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can View Recommendations";
    protected string $REQUIRED_PERMISSION_MANAGE = "Can Manage Recommendations";

    public function canEdit(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canDelete(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canToBeProcessedBoard(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canToBeScheduledBoard(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canToBeProcessed(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canToBeScheduled(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canToBeGiven(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canViewPrivateNotes(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }


    public function canAdd(IdentityInterface $user, $entity)
    {
        return true;
    }
}