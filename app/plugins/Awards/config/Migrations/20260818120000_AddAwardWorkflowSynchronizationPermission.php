<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Add least-privilege access to synchronize open award workflows.
 */
class AddAwardWorkflowSynchronizationPermission extends BaseMigration
{
    private const SYNC_PERMISSION = 'Can Synchronize Award Workflows';

    private const CROWN_ROLE = 'Ansteorran Crown';

    private const MANAGE_AWARDS_PERMISSION = 'Can Manage Awards';

    private const MANAGE_TODO_TEMPLATES_PERMISSION = 'Can Manage Bestowal To-Do Templates';

    private const APPROVAL_PROCESSES_POLICY = 'Awards\\Policy\\ApprovalProcessesTablePolicy';

    private const TODO_TEMPLATES_POLICY = 'Awards\\Policy\\BestowalTodoTemplatesTablePolicy';

    private const SYNC_PERMISSION_POLICIES = [
        [self::APPROVAL_PROCESSES_POLICY, 'canIndex'],
        [self::APPROVAL_PROCESSES_POLICY, 'canGridData'],
        [self::APPROVAL_PROCESSES_POLICY, 'canSyncOpenRecommendations'],
        [self::TODO_TEMPLATES_POLICY, 'canIndex'],
        [self::TODO_TEMPLATES_POLICY, 'canGridData'],
        [self::TODO_TEMPLATES_POLICY, 'canSyncOpenBestowals'],
    ];

    private const ADMIN_PERMISSION_POLICIES = [
        self::MANAGE_AWARDS_PERMISSION => [
            [self::APPROVAL_PROCESSES_POLICY, 'canSyncOpenRecommendations'],
        ],
        self::MANAGE_TODO_TEMPLATES_PERMISSION => [
            [self::TODO_TEMPLATES_POLICY, 'canSyncOpenBestowals'],
        ],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        $permissions->getConnection()->transactional(function (): void {
            $creatorId = $this->findCreatorId();
            $syncPermissionId = $this->ensureSyncPermission($creatorId);

            foreach (self::SYNC_PERMISSION_POLICIES as [$policyClass, $policyMethod]) {
                $this->ensurePolicyMapping($syncPermissionId, $policyClass, $policyMethod);
            }
            $this->ensureAdministratorPolicyMappings();

            $crownRole = TableRegistry::getTableLocator()->get('Roles')->find()
                ->where(['name' => self::CROWN_ROLE])
                ->first();
            if ($crownRole !== null) {
                $this->ensureRolePermission((int)$crownRole->id, $syncPermissionId, $creatorId);
            }
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        throw new RuntimeException(
            'AddAwardWorkflowSynchronizationPermission is irreversible because it adopts existing permission data.',
        );
    }

    /**
     * @param int $creatorId Audit member ID.
     * @return int
     */
    private function ensureSyncPermission(int $creatorId): int
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissions->find('withTrashed')
            ->where(['name' => self::SYNC_PERMISSION])
            ->first();
        $now = DateTime::now();
        $data = [
            'name' => self::SYNC_PERMISSION,
            'scoping_rule' => Permission::SCOPE_GLOBAL,
            'require_active_membership' => true,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => true,
            'is_super_user' => false,
            'requires_warrant' => true,
        ];

        if ($permission === null) {
            $permission = $permissions->newEntity($data);
            $permission->set('created', $now);
            $permission->set('created_by', $creatorId);
        } else {
            $permission = $permissions->patchEntity($permission, $data);
        }
        $permission->set('deleted', null);
        $permission->set('modified', $now);
        $permission->set('modified_by', $creatorId);
        $permission->setDirty('modified_by', true);
        $permissions->saveOrFail($permission);

        return (int)$permission->id;
    }

    /**
     * Preserve direct access for the existing administrator permissions.
     *
     * @return void
     */
    private function ensureAdministratorPolicyMappings(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        foreach (self::ADMIN_PERMISSION_POLICIES as $permissionName => $policies) {
            $permission = $permissions->find()->where(['name' => $permissionName])->first();
            if ($permission === null) {
                continue;
            }
            foreach ($policies as [$policyClass, $policyMethod]) {
                $this->ensurePolicyMapping((int)$permission->id, $policyClass, $policyMethod);
            }
        }
    }

    /**
     * @param int $permissionId Permission ID.
     * @param string $policyClass Policy class.
     * @param string $policyMethod Policy method.
     * @return void
     */
    private function ensurePolicyMapping(int $permissionId, string $policyClass, string $policyMethod): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $conditions = [
            'permission_id' => $permissionId,
            'policy_class' => $policyClass,
            'policy_method' => $policyMethod,
        ];
        if ($permissionPolicies->exists($conditions)) {
            return;
        }

        $permissionPolicies->saveOrFail($permissionPolicies->newEntity($conditions));
    }

    /**
     * @param int $roleId Role ID.
     * @param int $permissionId Permission ID.
     * @param int $creatorId Audit member ID.
     * @return void
     */
    private function ensureRolePermission(int $roleId, int $permissionId, int $creatorId): void
    {
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $conditions = [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ];
        if ($rolesPermissions->exists($conditions)) {
            return;
        }

        $rolesPermissions->saveOrFail($rolesPermissions->newEntity($conditions + [
            'created' => DateTime::now(),
            'created_by' => $creatorId,
        ]));
    }

    /**
     * @return int
     */
    private function findCreatorId(): int
    {
        $member = TableRegistry::getTableLocator()->get('Members')->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->first();
        if ($member === null) {
            throw new RuntimeException(
                'An existing member is required to create the workflow synchronization permission.',
            );
        }

        return (int)$member->id;
    }
}
