<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * Permissions seed.
 */
class DevLoadPermissionsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $createdByMemberId =  SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'name' => 'Can Authorize Armored Combat',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Armored Combat Field Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Rapier Combat',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Rapier Combat Field Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Youth Boffer 1',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Youth Boffer 2',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Youth Boffer 3',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Youth Boffer Junior Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Youth Boffer Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                'name' => 'Can Authorize Authorizing Rapier Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
        ];
    }

    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('permissions');
        $table->insert($data)->save();

        // Update scoping_rule for specific permissions by name
        $permissionsToUpdate = [
            'Can View Members',
            'Can View Member Details'
        ];
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        foreach ($permissionsToUpdate as $permissionName) {
            $permission = $permissionsTable->find()->where(['name' => $permissionName])->first();
            if ($permission) {
                $permission->scoping_rule = 'Branch and Children';
                $permissionsTable->saveOrFail($permission);
            }
        }
    }
}
