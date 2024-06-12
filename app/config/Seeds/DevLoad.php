<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Role seed.
 */
class DevLoad extends AbstractSeed
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
        $this->call('DevLoadBranchesSeed');
        $this->call('DevLoadRolesSeed');
        $this->call('DevLoadActivityGroupsSeed');
        $this->call('DevLoadActivitiesSeed');
        $this->call('DevLoadPermissionsSeed');
        $this->call('DevLoadRolesPermissionsSeed');
        $this->call('DevLoadMembersSeed');
        $this->call('DevLoadMemberRolesSeed');
        $this->call('DevLoadDepartmentsSeed');
        $this->call('DevLoadOfficesSeed');
        $this->call('DevLoadOfficersSeed');
        $this->call('DevLoadAppSettingsSeed');
    }
}