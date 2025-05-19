<?php

declare(strict_types=1);


use Migrations\BaseSeed;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * RolesPermissions seed.
 */
class InitRolesPermissionsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            [
                'permission_id' => SeedHelpers::getPermissionId("Can Do All But Is Not A Super User"), //1,
                'role_id' => SeedHelpers::getRoleId("Admin"), //1,
                'created_by' => 1,
            ]
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

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}