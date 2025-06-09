<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;
use App\Model\Entity\BaseEntity;

/**
 * Department policy
 */
class OfficerPolicy extends BasePolicy
{
    //public const SKIP_BASE = 'false';

    /**
     * Check if $user can see all officers in a branch
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canBranchOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can see all officers
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canWorkWithAllOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Check if $user can see all officers in their reporting tree
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canWorkWithOfficerReportingTree(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Check if $user can work with officers and deputies in their reporting chain
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canWorkWithOfficerDeputies(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Check if $user can only work with the directly reporting officers and deputies.
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canWorkWithOfficerDirectReports(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Check if $user can release officers or deputies from their office.
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canRelease(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Check if $user can request a warrant for the officer.
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canRequestWarrant(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($user->id == $entity->member_id) {
            return true;
        }
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Check if $user can see officers by warrant status
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canOfficersByWarrantStatus(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Check if $user can edit officers
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        //check if the entity has a branch_id
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        // if branchId is null, we cannot edit the officer

        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Check if $user can see officers
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Check if $user can assign officers
     *
     * @param KmpIdentityInterface $user The user.
     * @param BaseEntity $entity The entity.
     * @param mixed ...$optionalArgs Optional arguments.
     * @return bool
     */
    public function canAssign(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }
}