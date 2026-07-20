<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;

/**
 * Controller-level policy for WorkflowInstancesController.
 *
 * All actions are admin-only; requires super user or explicit policy grant.
 */
class WorkflowInstancesControllerPolicy implements BeforePolicyInterface
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

    public function canInstances(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    public function canGridData(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    public function canViewInstance(KmpIdentityInterface $user, mixed $resource): bool
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
