<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use Authorization\Policy\ResultInterface;

/**
 * Controller-level policy for WorkflowDefinitionsController.
 *
 * All actions are admin-only; requires super user or explicit policy grant.
 */
class WorkflowDefinitionsControllerPolicy implements BeforePolicyInterface
{
    /**
     * Allow super users to bypass action-specific checks.
     */
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

    /**
     * Check access to the workflow definition index.
     */
    public function canIndex(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to create workflow definitions.
     */
    public function canAdd(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to the workflow designer.
     */
    public function canDesigner(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to load a workflow version.
     */
    public function canLoadVersion(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to the workflow registry.
     */
    public function canRegistry(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to save a workflow definition.
     */
    public function canSave(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to publish a workflow definition.
     */
    public function canPublish(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to workflow versions.
     */
    public function canVersions(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to compare workflow versions.
     */
    public function canCompareVersions(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to activate or deactivate a workflow definition.
     */
    public function canToggleActive(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to create a workflow draft.
     */
    public function canCreateDraft(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * Check access to migrate active workflow instances.
     */
    public function canMigrateInstances(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, __FUNCTION__, $resource);
    }

    /**
     * App-setting names are workflow designer metadata, so index access is sufficient.
     */
    public function canAppSettings(KmpIdentityInterface $user, mixed $resource): bool
    {
        return $this->_hasPolicyForUrl($user, 'canIndex', $resource);
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
