<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Establish the least-privilege permission model for bestowal To-Do staff.
 */
class HardenBestowalTodoSecurity extends BaseMigration
{
    private const BESTOWAL_POLICY = 'Awards\\Policy\\BestowalPolicy';

    private const BESTOWALS_TABLE_POLICY = 'Awards\\Policy\\BestowalsTablePolicy';

    private const COURT_AGENDA_POLICY = 'Awards\\Policy\\CourtAgendaPolicy';

    private const COURT_AGENDAS_TABLE_POLICY = 'Awards\\Policy\\CourtAgendasTablePolicy';

    private const GATHERING_POLICY = 'App\\Policy\\GatheringPolicy';

    private const TODO_TEMPLATE_POLICY = 'Awards\\Policy\\BestowalTodoTemplatePolicy';

    private const TODO_TEMPLATES_TABLE_POLICY = 'Awards\\Policy\\BestowalTodoTemplatesTablePolicy';

    private const ADMIN_BESTOWALS = 'Can Administer Bestowals';

    private const ADMIN_COURT_AGENDAS = 'Can Administer Court Agendas';

    private const MANAGE_TODO_TEMPLATES = 'Can Manage Bestowal To-Do Templates';

    private const LEGACY_PERMISSION_NAMES = [
        'Can View Bestowals',
        'Can Manage Bestowals',
        'Can Prepare Scrolls',
        'Can Manage Court Schedule',
    ];

    private const DUPLICATE_PERMISSION_NAMES = [
        'Can View Bestowal (Branch and Children)',
        'Can Manage Bestowals (Branch and Children)',
    ];

    private const TIER_SCOPES = [
        'Crown' => Permission::SCOPE_BRANCH_ONLY,
        'Principality' => Permission::SCOPE_BRANCH_ONLY,
        'Baronial' => Permission::SCOPE_BRANCH_AND_CHILDREN,
    ];

    private const TODO_FUNCTIONS = [
        'Scroll' => 'Scroll Management',
        'Regalia' => 'Regalia Management',
        'Award Schedule' => 'Award Schedule Management',
        'Court Agenda' => 'Court Management',
        'Court Reporter' => 'Court Reporter',
    ];

    private const BESTOWAL_READ_POLICIES = [
        [self::BESTOWAL_POLICY, 'canView'],
        [self::BESTOWAL_POLICY, 'canIndex'],
        [self::BESTOWAL_POLICY, 'canGatheringBestowalsGridData'],
        [self::BESTOWAL_POLICY, 'canViewGatheringBestowals'],
        [self::BESTOWALS_TABLE_POLICY, 'canIndex'],
        [self::BESTOWALS_TABLE_POLICY, 'canExport'],
    ];

    private const COURT_AGENDA_READ_POLICIES = [
        [self::COURT_AGENDAS_TABLE_POLICY, 'canIndex'],
        [self::COURT_AGENDA_POLICY, 'canGathering'],
        [self::COURT_AGENDA_POLICY, 'canPrintAgenda'],
    ];

    private const COURT_AGENDA_MANAGE_POLICIES = [
        [self::COURT_AGENDAS_TABLE_POLICY, 'canIndex'],
        [self::COURT_AGENDA_POLICY, 'canGathering'],
        [self::COURT_AGENDA_POLICY, 'canPrintAgenda'],
        [self::COURT_AGENDA_POLICY, 'canEdit'],
        [self::COURT_AGENDA_POLICY, 'canImport'],
        [self::COURT_AGENDA_POLICY, 'canAddSegment'],
        [self::COURT_AGENDA_POLICY, 'canAddBlock'],
        [self::COURT_AGENDA_POLICY, 'canAddBestowal'],
        [self::COURT_AGENDA_POLICY, 'canMoveToRoaming'],
        [self::COURT_AGENDA_POLICY, 'canUpdateItem'],
        [self::COURT_AGENDA_POLICY, 'canMoveItem'],
        [self::COURT_AGENDA_POLICY, 'canRemoveItem'],
    ];

    private const SCHEDULE_POLICIES = [
        [self::BESTOWAL_POLICY, 'canManageCourtSchedule'],
        [self::BESTOWAL_POLICY, 'canBulkAssignGathering'],
        [self::BESTOWAL_POLICY, 'canGatheringsForBestowalAutoComplete'],
        [self::BESTOWAL_POLICY, 'canGatheringsForBestowalBulkAutoComplete'],
    ];

    private const BESTOWAL_ADMIN_POLICIES = [
        [self::BESTOWAL_POLICY, 'canView'],
        [self::BESTOWAL_POLICY, 'canIndex'],
        [self::BESTOWAL_POLICY, 'canGatheringBestowalsGridData'],
        [self::BESTOWAL_POLICY, 'canViewGatheringBestowals'],
        [self::BESTOWALS_TABLE_POLICY, 'canIndex'],
        [self::BESTOWALS_TABLE_POLICY, 'canExport'],
        [self::BESTOWAL_POLICY, 'canEdit'],
        [self::BESTOWAL_POLICY, 'canUpdateState'],
        [self::BESTOWAL_POLICY, 'canUpdateStates'],
        [self::BESTOWAL_POLICY, 'canTurboEditForm'],
        [self::BESTOWAL_POLICY, 'canTurboBulkEditForm'],
        [self::BESTOWAL_POLICY, 'canCourtSlotsForGathering'],
        [self::BESTOWAL_POLICY, 'canGatheringsForBestowalAutoComplete'],
        [self::BESTOWAL_POLICY, 'canGatheringsForBestowalBulkAutoComplete'],
        [self::BESTOWAL_POLICY, 'canCancel'],
        [self::BESTOWAL_POLICY, 'canAdHoc'],
        [self::BESTOWAL_POLICY, 'canViewHidden'],
        [self::BESTOWAL_POLICY, 'canPrepareScrolls'],
        [self::BESTOWAL_POLICY, 'canManageCourtSchedule'],
        [self::BESTOWAL_POLICY, 'canBulkAssignGathering'],
    ];

    private const TEMPLATE_ADMIN_POLICIES = [
        [self::TODO_TEMPLATE_POLICY, 'canIndex'],
        [self::TODO_TEMPLATE_POLICY, 'canView'],
        [self::TODO_TEMPLATE_POLICY, 'canEdit'],
        [self::TODO_TEMPLATE_POLICY, 'canDelete'],
        [self::TODO_TEMPLATES_TABLE_POLICY, 'canIndex'],
        [self::TODO_TEMPLATES_TABLE_POLICY, 'canAdd'],
        [self::TODO_TEMPLATES_TABLE_POLICY, 'canGridData'],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        $permissions->getConnection()->transactional(function (): void {
            $creatorId = $this->findCreatorId();
            $permissionIds = $this->ensurePermissions($creatorId);

            $this->migrateLegacyRoleGrants($permissionIds);
            $this->configurePolicyMappings($permissionIds);
            $this->configureRoles($permissionIds, $creatorId);
            $this->moveTemplateAdministration($permissionIds);
            $this->removeDuplicatePermissions();
        });
    }

    /**
     * This security migration intentionally cannot restore global workflow grants.
     *
     * @return void
     */
    public function down(): void
    {
        throw new RuntimeException(
            'HardenBestowalTodoSecurity is irreversible because rollback would restore over-broad authorization.',
        );
    }

    /**
     * @param int|null $creatorId Audit member ID.
     * @return array<string, int>
     */
    private function ensurePermissions(?int $creatorId): array
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $definitions = $this->permissionDefinitions();
        $ids = [];

        foreach ($definitions as $name => $definition) {
            $permission = $permissions->find()->where(['name' => $name])->first();
            $data = [
                'name' => $name,
                'scoping_rule' => $definition['scope'],
                'require_active_membership' => true,
                'require_active_background_check' => false,
                'require_min_age' => 0,
                'is_system' => true,
                'is_super_user' => false,
                'requires_warrant' => true,
                'modified' => DateTime::now(),
                'modified_by' => $creatorId,
            ];

            if ($permission === null) {
                $permission = $permissions->newEntity($data + [
                    'created' => DateTime::now(),
                    'created_by' => $creatorId,
                ]);
            } else {
                $permission = $permissions->patchEntity($permission, $data);
            }

            $permissions->saveOrFail($permission);
            $ids[$name] = (int)$permission->id;
        }

        foreach (self::LEGACY_PERMISSION_NAMES as $name) {
            $permission = $permissions->find()->where(['name' => $name])->first();
            if ($permission === null) {
                continue;
            }
            $permission = $permissions->patchEntity($permission, [
                'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                'require_active_membership' => true,
                'requires_warrant' => true,
                'modified' => DateTime::now(),
                'modified_by' => $creatorId,
            ]);
            $permissions->saveOrFail($permission);
            $ids[$name] = (int)$permission->id;
        }

        return $ids;
    }

    /**
     * @return array<string, array{scope: string, policies: array<int, array{0: string, 1: string}>}>
     */
    private function permissionDefinitions(): array
    {
        $definitions = [
            self::ADMIN_BESTOWALS => [
                'scope' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                'policies' => self::BESTOWAL_ADMIN_POLICIES,
            ],
            self::ADMIN_COURT_AGENDAS => [
                'scope' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                'policies' => self::COURT_AGENDA_MANAGE_POLICIES,
            ],
            self::MANAGE_TODO_TEMPLATES => [
                'scope' => Permission::SCOPE_GLOBAL,
                'policies' => self::TEMPLATE_ADMIN_POLICIES,
            ],
        ];

        foreach (self::TIER_SCOPES as $tier => $scope) {
            foreach (self::TODO_FUNCTIONS as $function => $suffix) {
                $policies = self::BESTOWAL_READ_POLICIES;
                if ($function === 'Scroll') {
                    $policies[] = [self::BESTOWAL_POLICY, 'canPrepareScrolls'];
                } elseif ($function === 'Award Schedule') {
                    $policies = array_merge($policies, self::SCHEDULE_POLICIES);
                } elseif ($function === 'Court Agenda') {
                    $policies = array_merge($policies, self::COURT_AGENDA_MANAGE_POLICIES);
                } elseif ($function === 'Court Reporter') {
                    $policies = array_merge($policies, self::COURT_AGENDA_READ_POLICIES);
                }

                $definitions[$tier . ' ' . $suffix] = [
                    'scope' => $scope,
                    'policies' => $policies,
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, int> $permissionIds Permission IDs by name.
     * @return void
     */
    private function configurePolicyMappings(array $permissionIds): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $definitions = $this->permissionDefinitions();

        $legacyPolicies = [
            'Can View Bestowals' => array_merge(
                self::BESTOWAL_READ_POLICIES,
                self::COURT_AGENDA_READ_POLICIES,
            ),
            'Can Manage Bestowals' => self::BESTOWAL_ADMIN_POLICIES,
            'Can Prepare Scrolls' => [
                [self::BESTOWAL_POLICY, 'canPrepareScrolls'],
            ],
            'Can Manage Court Schedule' => array_merge(
                self::SCHEDULE_POLICIES,
                self::COURT_AGENDA_MANAGE_POLICIES,
                [
                    [self::GATHERING_POLICY, 'canCreateScheduledActivity'],
                    [self::GATHERING_POLICY, 'canEditScheduledActivity'],
                ],
            ),
        ];

        foreach ($definitions as $name => $definition) {
            $this->replacePolicyMappings($permissionIds[$name], $definition['policies']);
        }
        foreach ($legacyPolicies as $name => $policies) {
            if (isset($permissionIds[$name])) {
                $this->replacePolicyMappings($permissionIds[$name], $policies);
            }
        }

        $manageAwards = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->where(['name' => 'Can Manage Awards'])
            ->first();
        if ($manageAwards !== null) {
            $permissionPolicies->deleteAll([
                'permission_id' => (int)$manageAwards->id,
                'policy_class IN' => [
                    self::TODO_TEMPLATE_POLICY,
                    self::TODO_TEMPLATES_TABLE_POLICY,
                ],
            ]);
        }
    }

    /**
     * @param int $permissionId Permission ID.
     * @param array<int, array{0: string, 1: string}> $policies Policy class/method pairs.
     * @return void
     */
    private function replacePolicyMappings(int $permissionId, array $policies): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionPolicies->deleteAll(['permission_id' => $permissionId]);

        foreach (array_unique($policies, SORT_REGULAR) as [$policyClass, $policyMethod]) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => $permissionId,
                'policy_class' => $policyClass,
                'policy_method' => $policyMethod,
            ]));
        }
    }

    /**
     * @param array<string, int> $permissionIds Permission IDs by name.
     * @return void
     */
    private function migrateLegacyRoleGrants(array $permissionIds): void
    {
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        $transfers = [
            'Can Manage Bestowals' => self::ADMIN_BESTOWALS,
            'Can Manage Court Schedule' => self::ADMIN_COURT_AGENDAS,
            'Can Manage Bestowals (Branch and Children)' => self::ADMIN_BESTOWALS,
            'Can View Bestowal (Branch and Children)' => 'Can View Bestowals',
        ];

        foreach ($transfers as $sourceName => $targetName) {
            $source = $permissions->find()->where(['name' => $sourceName])->first();
            if ($source === null || !isset($permissionIds[$targetName])) {
                continue;
            }
            $roleIds = $rolesPermissions->find()
                ->select(['role_id'])
                ->where(['permission_id' => (int)$source->id])
                ->all()
                ->extract('role_id')
                ->map(static fn($id): int => (int)$id)
                ->toList();
            foreach ($roleIds as $roleId) {
                $this->ensureRolePermission($roleId, $permissionIds[$targetName], $this->findCreatorId());
            }
        }
    }

    /**
     * @param array<string, int> $permissionIds Permission IDs by name.
     * @param int|null $creatorId Audit member ID.
     * @return void
     */
    private function configureRoles(array $permissionIds, ?int $creatorId): void
    {
        $requiredRoleConfigs = [];
        foreach (self::TIER_SCOPES as $tier => $scope) {
            foreach (self::TODO_FUNCTIONS as $function => $suffix) {
                $requiredRoleConfigs[$tier . ' ' . $function . ' Bestowal Todo'] = [
                    $tier . ' ' . $suffix,
                ];
            }
        }

        foreach ($requiredRoleConfigs as $roleName => $permissionNames) {
            $roleId = $this->ensureRole($roleName, $creatorId);
            $this->setManagedRolePermissions($roleId, $permissionNames, $permissionIds, $creatorId);
        }

        $existingRoleConfigs = [
            'Ansteorran Crown' => [
                'Crown Scroll Management',
                'Crown Regalia Management',
                'Crown Award Schedule Management',
                'Crown Court Management',
                'Crown Court Reporter',
                self::ADMIN_BESTOWALS,
            ],
            'Golden Staff' => [
                self::ADMIN_BESTOWALS,
                self::ADMIN_COURT_AGENDAS,
            ],
            'Stable Scroll' => ['Crown Scroll Management'],
            'Sable Scroll' => ['Crown Scroll Management'],
            'Court Herald' => ['Crown Court Management'],
        ];

        $roles = TableRegistry::getTableLocator()->get('Roles');
        foreach ($existingRoleConfigs as $roleName => $permissionNames) {
            $role = $roles->find()->where(['name' => $roleName])->first();
            if ($role !== null) {
                $this->setManagedRolePermissions(
                    (int)$role->id,
                    $permissionNames,
                    $permissionIds,
                    $creatorId,
                );
            }
        }
    }

    /**
     * @param int $roleId Role ID.
     * @param array<int, string> $permissionNames Desired managed permissions.
     * @param array<string, int> $permissionIds Permission IDs by name.
     * @param int|null $creatorId Audit member ID.
     * @return void
     */
    private function setManagedRolePermissions(
        int $roleId,
        array $permissionNames,
        array $permissionIds,
        ?int $creatorId,
    ): void {
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $managedIds = array_values($permissionIds);
        $duplicateIds = $this->duplicatePermissionIds();
        $managedIds = array_values(array_unique(array_merge($managedIds, $duplicateIds)));

        if ($managedIds !== []) {
            $rolesPermissions->deleteAll([
                'role_id' => $roleId,
                'permission_id IN' => $managedIds,
            ]);
        }
        foreach ($permissionNames as $permissionName) {
            $this->ensureRolePermission($roleId, $permissionIds[$permissionName], $creatorId);
        }
    }

    /**
     * @param int $roleId Role ID.
     * @param int $permissionId Permission ID.
     * @param int|null $creatorId Audit member ID.
     * @return void
     */
    private function ensureRolePermission(int $roleId, int $permissionId, ?int $creatorId): void
    {
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $existing = $rolesPermissions->find()->where([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ])->first();
        if ($existing !== null) {
            return;
        }

        $rolesPermissions->saveOrFail($rolesPermissions->newEntity([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created' => DateTime::now(),
            'created_by' => $creatorId,
        ]));
    }

    /**
     * @param array<string, int> $permissionIds Permission IDs by name.
     * @return void
     */
    private function moveTemplateAdministration(array $permissionIds): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $manageAwards = $permissions->find()->where(['name' => 'Can Manage Awards'])->first();
        if ($manageAwards === null) {
            return;
        }

        $roleIds = $rolesPermissions->find()
            ->select(['role_id'])
            ->where(['permission_id' => (int)$manageAwards->id])
            ->all()
            ->extract('role_id')
            ->map(static fn($id): int => (int)$id)
            ->toList();
        foreach ($roleIds as $roleId) {
            $this->ensureRolePermission(
                $roleId,
                $permissionIds[self::MANAGE_TODO_TEMPLATES],
                $this->findCreatorId(),
            );
        }
    }

    /**
     * @return void
     */
    private function removeDuplicatePermissions(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $ids = $this->duplicatePermissionIds();
        if ($ids === []) {
            return;
        }

        $permissionPolicies->deleteAll(['permission_id IN' => $ids]);
        $rolesPermissions->deleteAll(['permission_id IN' => $ids]);
        $permissions->deleteAll(['id IN' => $ids]);
    }

    /**
     * @return array<int>
     */
    private function duplicatePermissionIds(): array
    {
        return TableRegistry::getTableLocator()->get('Permissions')->find()
            ->select(['id'])
            ->where(['name IN' => self::DUPLICATE_PERMISSION_NAMES])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();
    }

    /**
     * @param string $name Role name.
     * @param int|null $creatorId Audit member ID.
     * @return int
     */
    private function ensureRole(string $name, ?int $creatorId): int
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['name' => $name])->first();
        if ($role === null) {
            $role = $roles->newEntity([
                'name' => $name,
                'is_system' => true,
                'created' => DateTime::now(),
                'created_by' => $creatorId,
            ]);
            $roles->saveOrFail($role);
        }

        return (int)$role->id;
    }

    /**
     * @return int|null
     */
    private function findCreatorId(): ?int
    {
        $member = TableRegistry::getTableLocator()->get('Members')->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->first();

        return $member === null ? null : (int)$member->id;
    }
}
