<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\KMP\TenantContext;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Gathering;
use App\Model\Entity\Permission;
use Cake\ORM\TableRegistry;

/**
 * GatheringActivity Policy
 *
 * Manages authorization for gathering activity operations.
 * Authorization is driven by the Roles → Permissions → Policies system.
 */
class GatheringActivityPolicy extends BasePolicy
{
    /**
     * @var array<int, array<int>>|null Request-local activity rule map.
     */
    private ?array $assignmentPermissionIds = null;

    /**
     * @var string|null Tenant owning the cached rule map.
     */
    private ?string $assignmentTenantId = null;

    /**
     * Activity-specific grants are evaluated in the gathering's branch, never globally by ID alone.
     *
     * @param \App\KMP\KmpIdentityInterface $user Identity
     * @param \App\Model\Entity\BaseEntity $entity Activity configuration
     * @param mixed ...$optionalArgs Parent gathering
     * @return bool
     */
    public function canAssign(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $gathering = $optionalArgs[0] ?? null;
        if (!$gathering instanceof Gathering || !$gathering->branch_id) {
            return false;
        }
        $tenantId = TenantContext::tryCurrent()?->id ?? 'standalone';
        if ($this->assignmentPermissionIds === null || $this->assignmentTenantId !== $tenantId) {
            $this->assignmentTenantId = $tenantId;
            $this->assignmentPermissionIds = [];
            $rules = TableRegistry::getTableLocator()->get('GatheringActivityAssignmentPermissions')->find()
                ->select(['gathering_activity_id', 'permission_id'])->all();
            foreach ($rules as $rule) {
                $this->assignmentPermissionIds[$rule->gathering_activity_id][] = (int)$rule->permission_id;
            }
        }
        $permissionIds = $this->assignmentPermissionIds[$entity->id] ?? [];
        if ($permissionIds === []) {
            return $user->checkCan('edit', $gathering);
        }
        foreach ($user->getPermissions() as $permission) {
            if (
                in_array((int)$permission->id, $permissionIds, true)
                && ($permission->scoping_rule === Permission::SCOPE_GLOBAL
                    || in_array((int)$gathering->branch_id, array_map('intval', $permission->branch_ids ?? []), true))
            ) {
                return true;
            }
        }

        return false;
    }
}
