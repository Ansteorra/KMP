<?php

declare(strict_types=1);

require_once __DIR__ . '/../Seeds/Lib/SeedHelpers.php';

use Migrations\BaseSeed;



/**
 * Role seed.
 */
class DevLoad extends BaseSeed
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
        $this->call('DevLoadBranchesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadRolesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadPermissionsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadPoliciesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadMembersSeed', ['source' => 'Seeds']);
        $this->call('DevLoadAppSettingsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadMemberRolesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadRolesPermissionsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadActivityGroupsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadActivitiesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadDepartmentsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadOfficesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadOfficersSeed', ['source' => 'Seeds']);
        $this->call('DevLoadAwardsDomainsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadAwardsLevelsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadAwardsAwardsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadAwardsEventsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadWarrantsSeed', ['source' => 'Seeds']);
    }
}