<?php

namespace Queue\Policy;

use Authorization\IdentityInterface;
use App\Policy\BasePolicy;

/**
 * Department policy
 */
class QueuedJobPolicy extends BasePolicy
{
    protected string $REQUIRED_PERMISSION = "Can Manage Queue Engine";

    public function canAddJob(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canResetJob(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canRemoveJob(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canProcesses(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canReset(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canFlush(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canHardReset(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canStats(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canView(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canViewClasses(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canImport(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canEdit(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canData(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canDelete(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canExecute(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canTest(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canMigrate(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canTerminate(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
    public function canCleanup(IdentityInterface $user, $entity)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION)) {
            return true;
        }
    }
}