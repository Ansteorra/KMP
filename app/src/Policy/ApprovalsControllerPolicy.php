<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;

/**
 * Controller-level policy for ApprovalsController.
 *
 * Approval viewing is open to all authenticated users.
 * Admin actions (allApprovals, reassign) require super user or policy grant.
 */
class ApprovalsControllerPolicy implements BeforePolicyInterface
{
    public function before(
        ?IdentityInterface $user,
        mixed $resource,
        string $action,
    ): ResultInterface|bool|null {
        if ($user instanceof KmpIdentityInterface && $user->isSuperUser()) {
            return true;
        }

        return null;
    }

    public function canApprovals(KmpIdentityInterface $user, mixed $resource): bool
    {
        return true;
    }

    public function canRecordApproval(KmpIdentityInterface $user, mixed $resource): bool
    {
        return true;
    }

    public function canAllApprovals(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    public function canAllApprovalsGridData(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Mobile approvals view — any authenticated user.
     */
    public function canMobileApprovals(KmpIdentityInterface $user, mixed $resource): bool
    {
        return true;
    }

    /**
     * Mobile approvals data API — any authenticated user.
     */
    public function canMobileApprovalsData(KmpIdentityInterface $user, mixed $resource): bool
    {
        return true;
    }

    public function canReassignApproval(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check if user has explicit policy grant for a URL action.
     */
    private function _hasPolicyForUrl(KmpIdentityInterface $user, string $method, mixed $resource): bool
    {
        if (!is_array($resource)) {
            return false;
        }

        $policyClass = static::class;
        $policies = $user->getPolicies();
        if (empty($policies)) {
            return false;
        }
        $policyClassData = $policies[$policyClass] ?? null;
        if (empty($policyClassData)) {
            return false;
        }
        $policyMethodData = $policyClassData[$method] ?? null;

        return !empty($policyMethodData);
    }
}
