<?php

namespace App\Policy;

use Entity\Member;
use Entity\Permission;
use Entity\Role;

use Authorization\IdentityInterface;
use Authorization\Policy\ResultInterface;
use Authorization\Policy\BeforePolicyInterface;

use Cake\Log\Log;


class BasePolicy implements BeforePolicyInterface
{
    protected string $REQUIRED_PERMISSION = 'OVERRIDE_ME';
    public function before(?IdentityInterface $user, mixed $resource, string $action): ResultInterface|bool|null
    {
        if ($this->_isSuperUser($user)) {
            return true;
        }
        return null;
    }

    protected function _getPermissions($user): ArrayAccess|array|null{
        return $user->getPermissions();
    }
    protected function _isSuperUser($user): bool{
        $permissions = $this->_getPermissions($user);
        foreach($permissions as $permission){
            if($permission->is_super_user){
                Log::debug('User is a super user');
                return true;
            }
        }
        Log::debug('User is not a super user');
        return false;
    }

    protected function _hasNamedPermission($user, string $permission_name): bool{
        Log::debug('Checking for permission: ' . $permission_name);
        if($this->_isSuperUser($user))
        {
            return true;
        }
        $permissions = $this->_getPermissions($user);
        foreach($permissions as $permission){
            if($permission->name == $permission_name){
                Log::debug('User has permission: ' . $permission_name);
                return true;
            }
        }
        return false;
    }

        /**
     * Check if $user can add RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\RolesPermissions $rolesPermissions
     * @return bool
     */
    public function canAdd(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    /**
     * Check if $user can edit RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\RolesPermissions $rolesPermissions
     * @return bool
     */
    public function canEdit(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    /**
     * Check if $user can delete RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\RolesPermissions $rolesPermissions
     * @return bool
     */
    public function canDelete(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    /**
     * Check if $user can view RolesPermissions
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\RolesPermissions $rolesPermissions
     * @return bool
     */
    public function canView(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    /**
     * Check if $user can view role
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\role $role
     * @return bool
     */
    public function canIndex(IdentityInterface $user, $entity)
    {
        return $this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION);
    }

    public function scopeIndex(IdentityInterface $user, $query)
    {
        if ($this->_hasNamedPermission($user, $this->REQUIRED_PERMISSION))
            return $query;
        else
            return $query->where(['id' => -1]);
    }
}