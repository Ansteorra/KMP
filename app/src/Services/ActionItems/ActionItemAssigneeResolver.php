<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\KMP\PermissionsLoader;
use App\Model\Entity\ActionItem;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * ActionItemAssigneeResolver - resolves "who can act" for an action item.
 *
 * Mirrors the assignee vocabulary used by the workflow approval subsystem
 * (permission, role, member, dynamic, policy) so existing eligibility concepts
 * are reused. Permission scope honours branch scoping via PermissionsLoader.
 * Office-based assignment is expressed through the `dynamic` type backed by a
 * plugin-provided resolver service, keeping core free of plugin dependencies.
 */
class ActionItemAssigneeResolver
{
    /**
     * Per-instance memo of resolved eligible member-id lists keyed by assignee
     * identity (type + config + branch). A single resolution pass — e.g. the
     * "My To-Dos" badge / list iterating every open item — typically references
     * the same handful of (permission, branch) groups thousands of times; this
     * collapses those duplicate reverse lookups to one query per distinct group
     * for the lifetime of the resolver (a single request/operation), so it never
     * serves stale "who can act" data across requests.
     *
     * @var array<string, array<int>>
     */
    protected array $memberIdMemo = [];

    /**
     * Per-instance cache of instantiated dynamic resolver services keyed by
     * class name, so the per-item eligibility loop does not re-instantiate the
     * same plugin resolver for every action item it evaluates.
     *
     * @var array<string, object|null>
     */
    protected array $dynamicServiceCache = [];

    /**
     * Determine whether a member is eligible to act on an action item.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @param int $memberId The member to test
     * @return bool
     */
    public function isMemberEligible(ActionItem $item, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        $config = $item->assignee_config ?? [];

        switch ($item->assignee_type) {
            case ActionItem::ASSIGNEE_TYPE_PERMISSION:
                return $this->isEligibleByPermission($config, $item->branch_id, $memberId);

            case ActionItem::ASSIGNEE_TYPE_ROLE:
                return $this->isEligibleByRole($config, $item->branch_id, $memberId);

            case ActionItem::ASSIGNEE_TYPE_MEMBER:
                return $memberId === (int)($config['member_id'] ?? 0);

            case ActionItem::ASSIGNEE_TYPE_DYNAMIC:
                return $this->isDynamicMemberEligible($item, $memberId);

            case ActionItem::ASSIGNEE_TYPE_POLICY:
                // Policy assignment falls back to the underlying permission pool.
                return $this->isEligibleByPermission($config, $item->branch_id, $memberId);

            default:
                return false;
        }
    }

    /**
     * Resolve the list of eligible member IDs for an action item.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @return array<int> Distinct eligible member IDs
     */
    public function getEligibleMemberIds(ActionItem $item): array
    {
        $config = $item->assignee_config ?? [];

        switch ($item->assignee_type) {
            case ActionItem::ASSIGNEE_TYPE_PERMISSION:
            case ActionItem::ASSIGNEE_TYPE_POLICY:
                return $this->membersByPermission($config, $item->branch_id);

            case ActionItem::ASSIGNEE_TYPE_ROLE:
                return $this->membersByRole($config, $item->branch_id);

            case ActionItem::ASSIGNEE_TYPE_MEMBER:
                $memberId = (int)($config['member_id'] ?? 0);

                return $memberId > 0 ? [$memberId] : [];

            case ActionItem::ASSIGNEE_TYPE_DYNAMIC:
                return $this->resolveDynamicMemberIds($item);

            default:
                return [];
        }
    }

    /**
     * Permission eligibility for a single member, branch-scoped when possible.
     *
     * @param array<string, mixed> $config Assignee config
     * @param int|null $branchId Resolved branch scope
     * @param int $memberId Member to test
     * @return bool
     */
    protected function isEligibleByPermission(array $config, ?int $branchId, int $memberId): bool
    {
        $permissionId = (int)($config['permission_id'] ?? 0);
        $permissionName = $config['permission'] ?? null;
        if ($permissionId <= 0 && ($permissionName === null || $permissionName === '')) {
            return false;
        }

        // A member's effective permissions are ALWAYS sourced from
        // PermissionsLoader so warrant, membership-status, scoping-rule, and
        // caching are honored — never query the permission tables directly for
        // a member's own permissions.
        $permissions = PermissionsLoader::getPermissions($memberId);

        if ($permissionId > 0) {
            $permission = $permissions[$permissionId] ?? null;

            return $permission !== null && $this->permissionCoversBranch($permission, $branchId);
        }

        foreach ($permissions as $permission) {
            if (
                (string)$permission->name === (string)$permissionName
                && $this->permissionCoversBranch($permission, $branchId)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a loaded permission's resolved scope covers a branch.
     *
     * A Global permission carries `branch_ids === null` (every branch); a scoped
     * permission lists the concrete branch ids it grants.
     *
     * @param object $permission A permission object from PermissionsLoader
     * @param int|null $branchId Branch to test, or null to ignore branch scope
     * @return bool
     */
    protected function permissionCoversBranch(object $permission, ?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        $branchIds = $permission->branch_ids ?? null;

        return $branchIds === null || in_array($branchId, $branchIds, true);
    }

    /**
     * Role eligibility for a single member.
     *
     * @param array<string, mixed> $config Assignee config
     * @param int|null $branchId Resolved branch scope
     * @param int $memberId Member to test
     * @return bool
     */
    protected function isEligibleByRole(array $config, ?int $branchId, int $memberId): bool
    {
        $roleName = $config['role'] ?? null;
        $roleId = (int)($config['role_id'] ?? 0);
        if (!$roleName && $roleId <= 0) {
            return false;
        }

        // Source the member's active roles from the canonical cached loader rather
        // than querying member_roles directly. When an ActionItem has a branch,
        // role eligibility is scoped to that branch.
        $roles = PermissionsLoader::getRoles($memberId);
        if ($roleId > 0) {
            $role = $roles[$roleId] ?? null;

            return $role !== null && $this->roleCoversBranch($role, $branchId);
        }

        foreach ($roles as $role) {
            if ((string)$role->name === (string)$roleName && $this->roleCoversBranch($role, $branchId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a loaded role assignment covers a branch.
     *
     * @param object $role Role object from PermissionsLoader.
     * @param int|null $branchId Branch to test, or null to ignore branch scope.
     * @return bool
     */
    protected function roleCoversBranch(object $role, ?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        return in_array($branchId, $role->branch_ids ?? [], true);
    }

    /**
     * Branch-scoped reverse permission lookup returning member IDs.
     *
     * @param array<string, mixed> $config Assignee config
     * @param int|null $branchId Resolved branch scope
     * @return array<int>
     */
    protected function membersByPermission(array $config, ?int $branchId): array
    {
        $permissionId = (int)($config['permission_id'] ?? 0);
        $permissionName = $config['permission'] ?? null;
        $permissionKey = $permissionId > 0 ? (string)$permissionId : 'n:' . (string)$permissionName;
        $memoKey = 'perm|' . $permissionKey . '|' . (string)$branchId;
        if (array_key_exists($memoKey, $this->memberIdMemo)) {
            return $this->memberIdMemo[$memoKey];
        }

        if ($permissionId > 0) {
            $query = PermissionsLoader::getMembersWithPermissionsQuery($permissionId, (int)$branchId);
            $this->memberIdMemo[$memoKey] = $query->all()->extract('id')->map(fn($id): int => (int)$id)->toList();

            return $this->memberIdMemo[$memoKey];
        }

        if (!$permissionName) {
            return $this->memberIdMemo[$memoKey] = [];
        }

        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $now = DateTime::now();

        return $this->memberIdMemo[$memoKey] = $membersTable->find()
            ->innerJoinWith('MemberRoles.Roles.Permissions')
            ->where(['Permissions.name' => $permissionName])
            ->where($this->activeRoleConditions($now))
            ->select(['Members.id'])
            ->distinct(['Members.id'])
            ->all()
            ->extract('id')
            ->map(fn($id): int => (int)$id)
            ->toList();
    }

    /**
     * Reverse role lookup returning member IDs.
     *
     * @param array<string, mixed> $config Assignee config
     * @param int|null $branchId Resolved branch scope
     * @return array<int>
     */
    protected function membersByRole(array $config, ?int $branchId): array
    {
        $roleName = $config['role'] ?? null;
        $roleId = (int)($config['role_id'] ?? 0);
        if (!$roleName && $roleId <= 0) {
            return [];
        }

        $memoKey = 'role|' . ($roleId > 0 ? $roleId : 'n:' . (string)$roleName) . '|' . (string)$branchId;
        if (array_key_exists($memoKey, $this->memberIdMemo)) {
            return $this->memberIdMemo[$memoKey];
        }

        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $now = DateTime::now();
        $query = $membersTable->find()
            ->innerJoinWith('MemberRoles.Roles')
            ->where($this->activeRoleConditions($now))
            ->select(['Members.id'])
            ->distinct(['Members.id']);

        if ($roleId > 0) {
            $query->where(['Roles.id' => $roleId]);
        } else {
            $query->where(['Roles.name' => $roleName]);
        }
        if ($branchId !== null) {
            $query->where(['MemberRoles.branch_id' => $branchId]);
        }

        return $this->memberIdMemo[$memoKey] = $query->all()->extract('id')->map(fn($id): int => (int)$id)->toList();
    }

    /**
     * Member-centric dynamic eligibility.
     *
     * Prefers an optional member-centric entry point on the dynamic resolver
     * service (`isMemberEligible(ActionItem, int): bool`). Plugin resolvers that
     * implement it resolve the acting member's scope once and match in memory,
     * avoiding a reverse "who can act" lookup per item — the cause of badge/list
     * query storms. Resolvers without it fall back to the list-based check.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @param int $memberId The member to test
     * @return bool
     */
    protected function isDynamicMemberEligible(ActionItem $item, int $memberId): bool
    {
        $service = $this->getDynamicService($item);
        if ($service !== null && method_exists($service, 'isMemberEligible')) {
            return (bool)$service->isMemberEligible($item, $memberId);
        }

        return in_array($memberId, $this->resolveDynamicMemberIds($item), true);
    }

    /**
     * Resolve (and per-instance cache) the dynamic resolver service for an item.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @return object|null The resolver service, or null when misconfigured
     */
    protected function getDynamicService(ActionItem $item): ?object
    {
        $config = $item->assignee_config ?? [];
        $serviceClass = $config['service'] ?? null;

        if (!$serviceClass) {
            return null;
        }

        if (array_key_exists($serviceClass, $this->dynamicServiceCache)) {
            return $this->dynamicServiceCache[$serviceClass];
        }

        if (!class_exists($serviceClass)) {
            Log::error("ActionItem {$item->id}: dynamic assignee service '{$serviceClass}' not found");

            return $this->dynamicServiceCache[$serviceClass] = null;
        }

        return $this->dynamicServiceCache[$serviceClass] = new $serviceClass();
    }

    /**
     * Resolve eligible member IDs via a configured dynamic resolver service.
     *
     * Expects assignee_config: {"service": "Plugin\\Service", "method": "resolve"}.
     * The callback receives the ActionItem and must return int[] of member IDs.
     * This is how office-based assignment is supported without a core→plugin
     * dependency.
     *
     * @param \App\Model\Entity\ActionItem $item The action item
     * @return array<int>
     */
    protected function resolveDynamicMemberIds(ActionItem $item): array
    {
        $config = $item->assignee_config ?? [];
        $serviceClass = $config['service'] ?? null;
        $method = $config['method'] ?? null;

        if (!$serviceClass || !$method) {
            Log::warning("ActionItem {$item->id}: dynamic assignee missing service/method config");

            return [];
        }

        // Memoize by the resolution identity (service/method + config + branch),
        // not the item id: distinct items that share the same dynamic assignee
        // group (e.g. every bestowal's "Given" check in one branch) resolve to
        // the same member set, so the badge/list loop hits one query per group.
        $memoKey = 'dyn|' . $serviceClass . '|' . $method . '|' . (string)$item->branch_id
            . '|' . md5((string)json_encode($config));
        if (array_key_exists($memoKey, $this->memberIdMemo)) {
            return $this->memberIdMemo[$memoKey];
        }

        $service = $this->getDynamicService($item);
        if ($service === null) {
            return $this->memberIdMemo[$memoKey] = [];
        }

        if (!method_exists($service, $method)) {
            Log::error("ActionItem {$item->id}: method '{$method}' not found on '{$serviceClass}'");

            return $this->memberIdMemo[$memoKey] = [];
        }

        $result = $service->$method($item);
        if (!is_array($result)) {
            Log::warning("ActionItem {$item->id}: dynamic resolver did not return an array");

            return $this->memberIdMemo[$memoKey] = [];
        }

        return $this->memberIdMemo[$memoKey] = array_values(array_unique(array_map('intval', $result)));
    }

    /**
     * Temporal "active role" conditions shared by eligibility queries.
     *
     * @param \Cake\I18n\DateTime $now Reference time
     * @return array<string, mixed>
     */
    protected function activeRoleConditions(DateTime $now): array
    {
        return [
            'OR' => [
                'MemberRoles.start_on IS' => null,
                'MemberRoles.start_on <=' => $now,
            ],
            'AND' => [
                'OR' => [
                    'MemberRoles.expires_on IS' => null,
                    'MemberRoles.expires_on >=' => $now,
                ],
            ],
        ];
    }
}
