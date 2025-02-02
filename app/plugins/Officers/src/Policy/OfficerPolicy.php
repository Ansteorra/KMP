<?php

declare(strict_types=1);

namespace Officers\Policy;

use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * Department policy
 */
class OfficerPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Officers";
    protected string $REQUIRED_VIEW_PERMISSION = "Can View Officers";
    protected string $REQUIRED_ASSIGN_PERMISSION = "Can Assign Officers";
    protected string $REQUIRED_RELEASE_PERMISSION = "Can Release Officers";

    public function canAdd(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_ASSIGN_PERMISSION)) {
            return true;
        }
        return false;
    }

    public function canBranchOfficers(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_ASSIGN_PERMISSION)) {
            return true;
        }
        return false;
    }


    public function canRelease(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
        if ($this->_hasNamedPermission($user, $this->REQUIRED_RELEASE_PERMISSION)) {
            return true;
        }
        return false;
    }

    public function canIndex(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        }
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_VIEW_PERMISSION);
        if ($canDo) {
            return true;
        }
        return false;
    }
    public function canOfficersByWarrantStatus(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        }
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_VIEW_PERMISSION);
        if ($canDo) {
            return true;
        }
        return false;
    }
    public function canOfficers(IdentityInterface $user, $entity)
    {
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
        if ($canDo) {
            return true;
        }
        $canDo = $this->_hasNamedPermission($user, $this->REQUIRED_VIEW_PERMISSION);
        if ($canDo) {
            return true;
        }
        return false;
    }
}