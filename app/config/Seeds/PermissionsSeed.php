<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Permissions seed.
 */
class PermissionsSeed extends AbstractSeed
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 4,
                'name' => 'Can Manage Authorization Types',
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
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
                'authorization_type_id' => NULL,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 10,
                'name' => 'Can Authorize Armored Combat',
                'authorization_type_id' => 1,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 11,
                'name' => 'Can Authorize Armored Combat Field Marshal',
                'authorization_type_id' => 2,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 12,
                'name' => 'Can Authorize Rapier Combat',
                'authorization_type_id' => 3,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 13,
                'name' => 'Can Authorize Rapier Combat Field Marshal',
                'authorization_type_id' => 4,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 14,
                'name' => 'Can Authorize Youth Boffer 1',
                'authorization_type_id' => 5,
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 15,
                'name' => 'Can Authorize Youth Boffer 2',
                'authorization_type_id' => 6,
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 16,
                'name' => 'Can Authorize Youth Boffer 3',
                'authorization_type_id' => 7,
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 17,
                'name' => 'Can Authorize Youth Boffer Junior Marshal',
                'authorization_type_id' => 9,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 18,
                'name' => 'Can Authorize Youth Boffer Marshal',
                'authorization_type_id' => 8,
                'require_active_membership' => 1,
                'require_active_background_check' => 1,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
            [
                'id' => 19,
                'name' => 'Can Authorize Authorizing Rapier Marshal',
                'authorization_type_id' => 10,
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 18,
                'system' => 0,
                'is_super_user' => 0,
                'requires_warrant' => 1,
            ],
        ];

        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}