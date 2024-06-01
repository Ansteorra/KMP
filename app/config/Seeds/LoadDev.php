<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Role seed.
 */
class LoadDev extends AbstractSeed
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
        $this->call('BranchesSeed');
        $this->call('RolesSeed');
        $this->call('AuthorizationGroupsSeed');
        $this->call('AuthorizationTypesSeed');
        $this->call('PermissionsSeed');
        $this->call('RolesPermissionsSeed');
        $this->call('MembersSeed');
        $this->call('MemberRolesSeed');
    }
}