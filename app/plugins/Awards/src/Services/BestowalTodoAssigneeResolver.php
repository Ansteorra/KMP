<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\PermissionsLoader;
use App\Model\Entity\ActionItem;
use App\Model\Entity\ActiveWindowBaseEntity;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Resolves the live set of members eligible to complete a bestowal to-do item.
 *
 * Bestowal to-do items are materialized as core ActionItems of the `dynamic`
 * assignee type so that "who can flip this check" is recomputed on every access
 * (officers rotate, role/permission assignments change). The branch context is
 * pre-resolved and stored on the ActionItem (`branch_id`); this resolver applies
 * the same branch-scoped role / permission / office logic used by the award
 * approval resolver, keeping the two flows consistent while leaving the core
 * action-item subsystem free of any Awards/Officers dependency.
 *
 * The dynamic resolver contract (see ActionItemAssigneeResolver) calls
 * `resolveMemberIds(ActionItem $item)` and expects an int[] of member IDs. The
 * per-item configuration is carried in `assignee_config`:
 *   { "kind": "role|permission|office", "source_id": <id>, "source_key": <string|null> }
 */
class BestowalTodoAssigneeResolver
{
    use LocatorAwareTrait;

    public const KIND_ROLE = 'role';

    public const KIND_PERMISSION = 'permission';

    public const KIND_OFFICE = 'office';

    /**
     * Request-local cache of a member's branch-scoped role assignments.
     *
     * Roles and offices are not covered by {@see PermissionsLoader} (which is
     * the canonical, cache-and-validation-backed source for *permissions*), so
     * the rarely-used role/office assignee kinds resolve them once per member
     * per request and match in memory. Permission eligibility never touches
     * these — it always flows through PermissionsLoader.
     *
     * @var array<int, array<int, array<int, bool>>>
     */
    protected static array $memberRoleScopeCache = [];

    /**
     * Request-local cache of a member's branch-scoped current offices.
     *
     * @var array<int, array<int, array<int, bool>>>
     */
    protected static array $memberOfficeScopeCache = [];

    /**
     * Clear the request-local role/office scope caches.
     *
     * Permission eligibility is served by {@see PermissionsLoader}, whose cache
     * is invalidated by the `security` cache group on role/permission writes;
     * this only resets the role/office lookups used by the non-permission kinds.
     * Useful for long-running CLI workers / tests that mutate state mid-process.
     *
     * @return void
     */
    public static function clearMemberScopeCache(): void
    {
        self::$memberRoleScopeCache = [];
        self::$memberOfficeScopeCache = [];
    }

    /**
     * Whether a member may complete a dynamic bestowal to-do, resolved
     * member-centrically (one scope lookup per member, matched in memory).
     *
     * This is the cheap counterpart to {@see self::resolveMemberIds()} used by
     * the eligibility hot path (My To-Dos badge/list). It never performs a
     * reverse "who holds this permission" query per item. Permission eligibility
     * is delegated to {@see PermissionsLoader::getPermissions()} so warrant,
     * membership-status, scoping, and caching are all honored consistently with
     * the rest of the application.
     *
     * @param \App\Model\Entity\ActionItem $item The action item to test.
     * @param int $memberId The member to test.
     * @return bool
     */
    public function isMemberEligible(ActionItem $item, int $memberId): bool
    {
        $config = $item->assignee_config ?? [];
        $kind = $config['kind'] ?? null;
        $sourceId = (int)($config['source_id'] ?? 0);
        $branchId = $item->branch_id;

        if ($memberId <= 0 || $sourceId <= 0 || $branchId === null) {
            return false;
        }

        $branchId = (int)$branchId;

        return match ($kind) {
            self::KIND_PERMISSION => $this->memberHasPermission($memberId, $sourceId, $branchId),
            self::KIND_ROLE => isset($this->memberRoleScope($memberId)[$branchId][$sourceId]),
            self::KIND_OFFICE => isset($this->memberOfficeScope($memberId)[$branchId][$sourceId]),
            default => false,
        };
    }

    /**
     * Permission eligibility via the canonical PermissionsLoader.
     *
     * PermissionsLoader is the single source of truth for a member's effective
     * permissions: it validates warrants, membership status, and role windows,
     * resolves the permission's scoping rule into concrete branch ids, and
     * caches the result cross-request (`member_permissions`, invalidated by the
     * `security` cache group). A Global-scoped permission carries
     * `branch_ids === null`, meaning every branch.
     *
     * @param int $memberId The member to test.
     * @param int $permissionId The required permission id.
     * @param int $branchId The branch the to-do is scoped to.
     * @return bool
     */
    protected function memberHasPermission(int $memberId, int $permissionId, int $branchId): bool
    {
        $permissions = PermissionsLoader::getPermissions($memberId);
        if (!isset($permissions[$permissionId])) {
            return false;
        }

        $branchIds = $permissions[$permissionId]->branch_ids ?? null;

        return $branchIds === null || in_array($branchId, $branchIds, true);
    }

    /**
     * Resolve (and request-cache) a member's branch-indexed role assignments.
     *
     * @param int $memberId The member to resolve.
     * @return array<int, array<int, bool>> branchId => [roleId => true]
     */
    protected function memberRoleScope(int $memberId): array
    {
        if (isset(self::$memberRoleScopeCache[$memberId])) {
            return self::$memberRoleScopeCache[$memberId];
        }

        // Source roles (and their assignment branches) from the canonical, cached,
        // temporally-validated loader rather than querying member_roles directly.
        $roleIdsByBranch = [];
        foreach (PermissionsLoader::getRoles($memberId) as $role) {
            $roleId = (int)$role->id;
            if ($roleId <= 0) {
                continue;
            }
            foreach ($role->branch_ids as $branchId) {
                $branchId = (int)$branchId;
                if ($branchId > 0) {
                    $roleIdsByBranch[$branchId][$roleId] = true;
                }
            }
        }

        return self::$memberRoleScopeCache[$memberId] = $roleIdsByBranch;
    }

    /**
     * Resolve (and request-cache) a member's branch-indexed current offices.
     *
     * @param int $memberId The member to resolve.
     * @return array<int, array<int, bool>> branchId => [officeId => true]
     */
    protected function memberOfficeScope(int $memberId): array
    {
        if (isset(self::$memberOfficeScopeCache[$memberId])) {
            return self::$memberOfficeScopeCache[$memberId];
        }

        $now = DateTime::now();
        $officeIdsByBranch = [];
        $rows = $this->fetchTable('Officers.Officers')->find()
            ->select(['branch_id' => 'Officers.branch_id', 'office_id' => 'Officers.office_id'])
            ->where([
                'Officers.member_id' => $memberId,
                'Officers.status' => ActiveWindowBaseEntity::CURRENT_STATUS,
                'Officers.start_on <=' => $now,
                'OR' => [
                    'Officers.expires_on IS' => null,
                    'Officers.expires_on >=' => $now,
                ],
            ])
            ->enableHydration(false)
            ->all();
        foreach ($rows as $row) {
            $branchId = (int)($row['branch_id'] ?? 0);
            $officeId = (int)($row['office_id'] ?? 0);
            if ($branchId > 0 && $officeId > 0) {
                $officeIdsByBranch[$branchId][$officeId] = true;
            }
        }

        return self::$memberOfficeScopeCache[$memberId] = $officeIdsByBranch;
    }

    /**
     * Resolve eligible member IDs for a dynamic bestowal to-do action item.
     *
     * @param \App\Model\Entity\ActionItem $item The action item to resolve.
     * @return array<int> Distinct eligible member IDs.
     */
    public function resolveMemberIds(ActionItem $item): array
    {
        $config = $item->assignee_config ?? [];
        $kind = $config['kind'] ?? null;
        $sourceId = (int)($config['source_id'] ?? 0);
        $branchId = $item->branch_id;

        if ($sourceId <= 0 || $branchId === null) {
            return [];
        }

        return match ($kind) {
            self::KIND_ROLE => $this->membersByRole($sourceId, (int)$branchId),
            self::KIND_PERMISSION => $this->membersByPermission($sourceId, (int)$branchId),
            self::KIND_OFFICE => $this->membersByOffice($sourceId, (int)$branchId),
            default => [],
        };
    }

    /**
     * Active members holding a role within a branch.
     *
     * @param int $roleId Role ID.
     * @param int $branchId Branch scope.
     * @return array<int>
     */
    protected function membersByRole(int $roleId, int $branchId): array
    {
        return $this->fetchTable('MemberRoles')->find('current')
            ->where([
                'MemberRoles.role_id' => $roleId,
                'MemberRoles.branch_id' => $branchId,
            ])
            ->select(['MemberRoles.member_id'])
            ->distinct(['MemberRoles.member_id'])
            ->all()
            ->extract('member_id')
            ->map(fn($id): int => (int)$id)
            ->toList();
    }

    /**
     * Members who hold a permission within a branch.
     *
     * Delegates to {@see PermissionsLoader::getMembersWithPermissionsQuery()} —
     * the canonical, scope-aware reverse lookup — so warrant/membership/scoping
     * validation is applied consistently and never duplicated here.
     *
     * @param int $permissionId Permission ID.
     * @param int $branchId Branch scope.
     * @return array<int>
     */
    protected function membersByPermission(int $permissionId, int $branchId): array
    {
        return PermissionsLoader::getMembersWithPermissionsQuery($permissionId, $branchId)
            ->all()
            ->extract('id')
            ->map(fn($id): int => (int)$id)
            ->toList();
    }

    /**
     * Current office holders for an office within a branch.
     *
     * @param int $officeId Office ID.
     * @param int $branchId Branch scope.
     * @return array<int>
     */
    protected function membersByOffice(int $officeId, int $branchId): array
    {
        $now = DateTime::now();

        return $this->fetchTable('Officers.Officers')->find()
            ->where([
                'Officers.office_id' => $officeId,
                'Officers.branch_id' => $branchId,
                'Officers.status' => ActiveWindowBaseEntity::CURRENT_STATUS,
                'Officers.start_on <=' => $now,
                'OR' => [
                    'Officers.expires_on IS' => null,
                    'Officers.expires_on >=' => $now,
                ],
            ])
            ->select(['Officers.member_id'])
            ->distinct(['Officers.member_id'])
            ->all()
            ->extract('member_id')
            ->map(fn($id): int => (int)$id)
            ->toList();
    }
}
