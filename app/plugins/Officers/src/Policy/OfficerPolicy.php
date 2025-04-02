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
    public const SKIP_BASE = 'true';

    public function canBranchOfficers(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }


    public function canRelease(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canRequestWarrant(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canOfficersByWarrantStatus(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canEdit(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canOfficers(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAssignMyDeputies(IdentityInterface $user, $entity, ...$optionalArgs)
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
    public function canAssignMyDirectReports(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }
    public function canAssignMyReportTree(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }

    public function canAssign(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }
}