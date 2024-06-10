<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Role seed.
 */
class InitMigrationSeed extends AbstractSeed
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
        $this->call('InitBranchesSeed');
        $this->call('InitRolesSeed');
        $this->call('InitPermissionsSeed');
        $this->call('InitRolesPermissionsSeed');
        $this->call('InitMembersSeed');
        $this->call('InitMemberRolesSeed');
    }
}
