<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Map court agenda controller actions to existing Awards permissions.
 */
class MapCourtAgendaPolicies extends BaseMigration
{
    private const POLICY_CLASS = 'Awards\\Policy\\CourtAgendaPolicy';

    private const TABLE_POLICY_CLASS = 'Awards\\Policy\\CourtAgendasTablePolicy';

    private const VIEW_METHODS = [
        'canGathering',
        'canPrintAgenda',
    ];

    private const MANAGE_METHODS = [
        'canEdit',
        'canImport',
        'canAddSegment',
        'canAddBlock',
        'canUpdateItem',
        'canMoveItem',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->mapMethods('Can View Bestowals', self::POLICY_CLASS, self::VIEW_METHODS);
        $this->mapMethods('Can View Bestowals', self::TABLE_POLICY_CLASS, ['canIndex']);
        $this->mapMethods('Can Manage Court Schedule', self::POLICY_CLASS, self::MANAGE_METHODS);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionIds = $permissions->find()
            ->select(['id'])
            ->where(['name IN' => ['Can View Bestowals', 'Can Manage Court Schedule']])
            ->all()
            ->extract('id')
            ->toList();
        if ($permissionIds === []) {
            return;
        }

        $permissionPolicies->deleteAll([
            'permission_id IN' => $permissionIds,
            'policy_class IN' => [self::POLICY_CLASS, self::TABLE_POLICY_CLASS],
        ]);
    }

    /**
     * @param string $permissionName Permission name.
     * @param string $policyClass Policy class.
     * @param array<int, string> $policyMethods Policy methods.
     * @return void
     */
    private function mapMethods(string $permissionName, string $policyClass, array $policyMethods): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()
            ->where(['name' => $permissionName])
            ->first();
        if ($permission === null) {
            return;
        }

        foreach ($policyMethods as $policyMethod) {
            $exists = $permissionPolicies->find()
                ->where([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyClass,
                    'policy_method' => $policyMethod,
                ])
                ->first();
            if ($exists !== null) {
                continue;
            }

            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
                'policy_method' => $policyMethod,
            ]));
        }
    }
}
