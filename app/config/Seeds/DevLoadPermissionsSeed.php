<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Permissions seed.
 */
class DevLoadPermissionsSeed extends BaseSeed
{
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
        $data = [
            [
                'id' => 200,
                'name' => 'Can Authorize Armored Combat',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 201,
                'name' => 'Can Authorize Armored Combat Field Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 202,
                'name' => 'Can Authorize Rapier Combat',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 203,
                'name' => 'Can Authorize Rapier Combat Field Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 204,
                'name' => 'Can Authorize Youth Boffer 1',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 205,
                'name' => 'Can Authorize Youth Boffer 2',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 206,
                'name' => 'Can Authorize Youth Boffer 3',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 207,
                'name' => 'Can Authorize Youth Boffer Junior Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 208,
                'name' => 'Can Authorize Youth Boffer Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 209,
                'name' => 'Can Authorize Authorizing Rapier Marshal',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'is_system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}
