<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * RolesPermissions seed.
 */
class DevLoadRolesPermissionsSeed extends BaseSeed
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
                'permission_id' => 2,
                'role_id' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 3,
                'role_id' => 1,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 200,
                'role_id' => 201,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 201,
                'role_id' => 201,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 202,
                'role_id' => 201,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 203,
                'role_id' => 201,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 202,
                'role_id' => 202,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 203,
                'role_id' => 202,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 202,
                'role_id' => 204,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 203,
                'role_id' => 204,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 200,
                'role_id' => 205,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 201,
                'role_id' => 205,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 209,
                'role_id' => 201,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'permission_id' => 209,
                'role_id' => 202,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}
