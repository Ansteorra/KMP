<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;
use App\Model\Entity\BaseEntity;

/**
 * Officer Authorization Policy
 *
 * Controls entity-level access for Officer operations including assignment, release,
 * warrant management, and hierarchical access control. Implements dual ownership
 * model (self-access + administrative access) and office-specific authorization.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficerPolicy extends BasePolicy
{
    /**
     * Check if user can view branch officers.
     * All authenticated members can view branch officers (public information).
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Context entity
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canBranchOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        // All authenticated members can view branch officers
        return $user->getIdentifier() !== null;
    }

    /**
     * Check if user can view member officers. Members can view their own assignments.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity with member_id
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canMemberOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can work with all officers across the organization.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Context entity
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canWorkWithAllOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can work with officers in their reporting tree.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity
     * @param mixed ...$optionalArgs [0] = branchId, [1] = doGrantCheck flag
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
        if ($hasPolicy) {
            return true;
        }
        return false;
    }

    /**
     * Check if user can work with officer deputies.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity
     * @param mixed ...$optionalArgs [0] = branchId, [1] = doGrantCheck flag
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
        if ($hasPolicy) {
            return true;
        }
        return false;
    }

    /**
     * Check if user can work with direct report officers.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity
     * @param mixed ...$optionalArgs [0] = branchId, [1] = doGrantCheck flag
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
        if ($hasPolicy) {
            return true;
        }
        return false;
    }

    /**
     * Check if user can release an officer. Validates both permission and office access.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity to release
     * @param mixed ...$optionalArgs [0] = branchId override
     * @return bool
     */
    public function canRelease(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
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
     * Check if user can request warrant. Members can request for their own assignments.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity
     * @param mixed ...$optionalArgs [0] = branchId override
     * @return bool
     */
    public function canRequestWarrant(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($user->id == $entity->member_id) {
            return true;
        }
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
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
     * Check if user can view officers by warrant status.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Context entity
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canOfficersByWarrantStatus(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can edit an officer. Validates both permission and office access.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Officer entity to edit
     * @param mixed ...$optionalArgs [0] = branchId override
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }

        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
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
     * Check if user has general officer access.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Context entity
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can assign officers within branch context.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity $entity Assignment context entity
     * @param mixed ...$optionalArgs [0] = branchId
     * @return bool
     */
    public function canAssign(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }
}
