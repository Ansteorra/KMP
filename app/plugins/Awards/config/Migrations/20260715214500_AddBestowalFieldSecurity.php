<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Restrict protected bestowal fields to Crown and Crown Court Management.
 */
class AddBestowalFieldSecurity extends BaseMigration
{
    private const CROWN_FIELD_PERMISSION = 'Crown Bestowal Field Access';

    private const COURT_PERMISSION = 'Crown Court Management';

    private const CROWN_ROLE = 'Ansteorran Crown';

    private const POLICY_CLASS = 'Awards\\Policy\\BestowalPolicy';

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        $permissions->getConnection()->transactional(function (): void {
            $creatorId = $this->findCreatorId();
            $crownPermissionId = $this->ensureCrownFieldPermission($creatorId);
            $courtPermission = TableRegistry::getTableLocator()->get('Permissions')->find()
                ->where(['name' => self::COURT_PERMISSION])
                ->first();
            if ($courtPermission === null) {
                throw new RuntimeException('Crown Court Management permission is required.');
            }

            $this->ensurePolicyMapping($crownPermissionId, 'canAccessHeraldNotes');
            $this->ensurePolicyMapping($crownPermissionId, 'canAccessCrownFields');
            $this->ensurePolicyMapping((int)$courtPermission->id, 'canAccessHeraldNotes');
            $this->ensureCrownRolePermission($crownPermissionId, $creatorId);
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');

        $courtPermission = $permissions->find()->where(['name' => self::COURT_PERMISSION])->first();
        if ($courtPermission !== null) {
            $permissionPolicies->deleteAll([
                'permission_id' => (int)$courtPermission->id,
                'policy_class' => self::POLICY_CLASS,
                'policy_method' => 'canAccessHeraldNotes',
            ]);
        }

        $crownPermission = $permissions->find()->where(['name' => self::CROWN_FIELD_PERMISSION])->first();
        if ($crownPermission === null) {
            return;
        }

        $permissionPolicies->deleteAll(['permission_id' => (int)$crownPermission->id]);
        $rolesPermissions->deleteAll(['permission_id' => (int)$crownPermission->id]);
        $permissions->deleteOrFail($crownPermission);
    }

    private function ensureCrownFieldPermission(?int $creatorId): int
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissions->find()->where(['name' => self::CROWN_FIELD_PERMISSION])->first();
        $data = [
            'name' => self::CROWN_FIELD_PERMISSION,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
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

        return (int)$permission->id;
    }

    private function ensurePolicyMapping(int $permissionId, string $policyMethod): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $conditions = [
            'permission_id' => $permissionId,
            'policy_class' => self::POLICY_CLASS,
            'policy_method' => $policyMethod,
        ];
        if ($permissionPolicies->exists($conditions)) {
            return;
        }

        $permissionPolicies->saveOrFail($permissionPolicies->newEntity($conditions));
    }

    private function ensureCrownRolePermission(int $permissionId, ?int $creatorId): void
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['name' => self::CROWN_ROLE])->first();
        if ($role === null) {
            $role = $roles->saveOrFail($roles->newEntity([
                'name' => self::CROWN_ROLE,
                'is_system' => true,
                'created' => DateTime::now(),
                'created_by' => $creatorId,
            ]));
        }

        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $conditions = [
            'role_id' => (int)$role->id,
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

    private function findCreatorId(): ?int
    {
        $member = TableRegistry::getTableLocator()->get('Members')->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->first();

        return $member === null ? null : (int)$member->id;
    }
}
