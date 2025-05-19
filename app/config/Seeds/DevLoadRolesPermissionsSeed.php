<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php';

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
        $data = $this->getData();
        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {

        $adminId =  SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Manage Roles'),
                'role_id' =>  SeedHelpers::getRoleId('Admin'), //1
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Manage Permissions'), //3
                'role_id' => SeedHelpers::getRoleId('Admin'), //1,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat'), //200,
                'role_id' => SeedHelpers::getRoleId('Kingdom Earl Marshal'), //201,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat Field Marshal'), //201,
                'role_id' => SeedHelpers::getRoleId('Kingdom Earl Marshal'), //201,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat'), //202,
                'role_id' => SeedHelpers::getRoleId('Kingdom Earl Marshal'), //201,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat Field Marshal'), //203,
                'role_id' => SeedHelpers::getRoleId('Kingdom Earl Marshal'), //201,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat'), //202,
                'role_id' => SeedHelpers::getRoleId('Kingdom Rapier Marshal'), //202,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat Field Marshal'), //203,
                'role_id' => SeedHelpers::getRoleId('Kingdom Rapier Marshal'), //202,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat'), //202,
                'role_id' => SeedHelpers::getRoleId('Authorizing Rapier Marshal'), //204,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Rapier Combat Field Marshal'), //203,
                'role_id' => SeedHelpers::getRoleId('Authorizing Rapier Marshal'), //204,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat'), //200,
                'role_id' => SeedHelpers::getRoleId('Authorizing Armored Marshal'), //205,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Armored Combat Field Marshal'), //201,
                'role_id' => SeedHelpers::getRoleId('Authorizing Armored Marshal'), //205,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Authorizing Rapier Marshal'), //209,
                'role_id' => SeedHelpers::getRoleId('Kingdom Earl Marshal'), //201,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Authorize Authorizing Rapier Marshal'), //209,
                'role_id' => SeedHelpers::getRoleId('Kingdom Rapier Marshal'), //202,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId('Can Manage Members'), //6,
                'role_id' => SeedHelpers::getRoleId('User Manager'), //207,
                'created' => DateTime::now(),
                'created_by' => $adminId,
            ],
        ];
    }
}