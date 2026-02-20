<?php

declare(strict_types=1);


use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * RolesPermissions seed.
 */
class InitRolesPermissionsSeed extends BaseSeed
{
    /**
     * Provide seed rows mapping the Admin role to initial permissions.
     *
     * Returns an array of associative arrays, each representing a row for the roles_permissions table with the keys:
     * `permission_id` (permission identifier), `role_id` (role identifier), and `created_by` (user ID who created the mapping).
     *
     * @return array An array of seed rows: one linking Admin to "Is Super User" and one linking Admin to "Can Do All But Is Not A Super User".
     */
    public function getData(): array
    {
        return [
            [
                'permission_id' => SeedHelpers::getPermissionId("Is Super User"),
                'role_id' => SeedHelpers::getRoleId("Admin"),
                'created_by' => 1,
                'created' => DateTime::now(),
            ],
            [
                'permission_id' => SeedHelpers::getPermissionId("Can Do All But Is Not A Super User"),
                'role_id' => SeedHelpers::getRoleId("Admin"),
                'created_by' => 1,
                'created' => DateTime::now(),
            ]
        ];
    }

    /**
     * Inserts the initial role-permission mappings into the roles_permissions table.
     *
     * Persists this seed's data to the database so the application has the required role-permission records. 
     */
    public function run(): void
    {
        $data = $this->getData();

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}