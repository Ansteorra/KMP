<?php

declare(strict_types=1);


use Migrations\AbstractSeed;



/**
 * Permissions seed.
 */
class InitPermissionsSeed extends AbstractSeed
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
                'id' => 1,
                'name' => 'Is Super User',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 1,
                'requires_warrant' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Can Manage Roles',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Can Manage Permissions',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 4,
                'name' => 'Can Manage Activities',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 5,
                'name' => 'Can Manage Branches',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 6,
                'name' => 'Can Manage Settings',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 7,
                'name' => 'Can Manage Members',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 8,
                'name' => 'Can View Reports',
                'activity_id' => NULL,
                'require_active_membership' => 0,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 0,
            ],
            [
                'id' => 9,
                'name' => 'Can Revoke Authorizations',
                'activity_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ]
        ];

        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}
