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

    public function canIndex(IdentityInterface $user, $entity, ...$args)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canViewSubmittedByMember(IdentityInterface $user, $entity, ...$args)
    {
        $member_id = $args[2]['member_id'];
        $canDo = $member_id == $user->get('id');
        return $canDo || $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canViewSubmittedForMember(IdentityInterface $user, $entity, ...$args)
    {
        //$member_id = $args[2]['member_id'];
        //$canDo = $member_id == $user->get('id');
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canViewEventRecommendations(IdentityInterface $user, $entity, ...$args)
    {
        //$member_id = $args[2]['member_id'];
        //$canDo = $member_id == $user->get('id');
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canExport(IdentityInterface $user, $entity, ...$args)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canEdit(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canUseBoard(IdentityInterface $user, $entity, ...$args)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canViewHidden(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canDelete(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canViewPrivateNotes(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function canAddNote(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canUpdateStates(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION_MANAGE);
    }

    public function canAdd(IdentityInterface $user, $entity)
    {
        return true;
    }
}