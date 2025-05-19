<?php

declare(strict_types=1);

require_once __DIR__ . '/Lib/SeedHelpers.php';

use Migrations\BaseSeed;

/**
 * Role seed.
 */
class InitMigrationSeed extends BaseSeed
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
        $this->call('InitBranchesSeed', ['source' => 'Seeds']);
        $this->call('InitMembersSeed', ['source' => 'Seeds']);
        $this->call('InitRolesSeed', ['source' => 'Seeds']);
        $this->call('InitPermissionsSeed', ['source' => 'Seeds']);
        $this->call('InitRolesPermissionsSeed', ['source' => 'Seeds']);
        $this->call('InitMemberRolesSeed', ['source' => 'Seeds']);
    }
}