<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\KMP\TenantContext;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
use App\Model\Entity\Permission;
use App\Services\GatheringActivityDelegationRegistry;
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
     * Native activity grants use the host branch; delegated owner grants supply their own scoped context.
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

        return $this->canAssignNatively($user, $entity, $gathering)
            || ($entity instanceof GatheringActivity
                && GatheringActivityDelegationRegistry::canAssign($user, $entity, $gathering));
    }

    /**
     * Evaluate a catalogue in one operation without repeating unconfigured activity fallbacks.
     *
     * @param array<\App\Model\Entity\GatheringActivity> $activities Catalogue candidates.
     */
    public function canAssignAny(KmpIdentityInterface $user, array $activities, Gathering $gathering): bool
    {
        if (!$gathering->branch_id || $activities === []) {
            return false;
        }
        if ($user->isSuperUser()) {
            return true;
        }
        $this->loadAssignmentPermissions();
        $canEditGathering = null;
        foreach ($activities as $activity) {
            if (($this->assignmentPermissionIds[$activity->id] ?? []) === []) {
                $canEditGathering ??= (new GatheringPolicy())->canEdit($user, $gathering);
                if ($canEditGathering) {
                    return true;
                }
            } elseif ($this->canAssignNatively($user, $activity, $gathering)) {
                return true;
            }
        }

        return GatheringActivityDelegationRegistry::canAssignAny($user, $activities, $gathering);
    }

    /** Native catalogue rules; a branch override requires an already-validated owner grant. */
    public function canAssignNatively(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        Gathering $gathering,
        ?int $permissionBranchId = null,
    ): bool {
        if ($user->isSuperUser()) {
            return true;
        }
        $this->loadAssignmentPermissions();
        $permissionIds = $this->assignmentPermissionIds[$entity->id] ?? [];
        if ($permissionIds === []) {
            return (new GatheringPolicy())->canEdit($user, $gathering);
        }
        foreach ($user->getPermissions() as $permission) {
            if (
                in_array((int)$permission->id, $permissionIds, true)
                && ($permission->scoping_rule === Permission::SCOPE_GLOBAL
                    || in_array(
                        $permissionBranchId ?? (int)$gathering->branch_id,
                        array_map('intval', $permission->branch_ids ?? []),
                        true,
                    ))
            ) {
                return true;
            }
        }

        return false;
    }

    /** Load native rules once for this policy instance and tenant. */
    private function loadAssignmentPermissions(): void
    {
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
    }
}
