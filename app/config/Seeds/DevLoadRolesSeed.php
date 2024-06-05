<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Roles seed.
 */
class DevLoadRolesSeed extends AbstractSeed
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
                'id' => 200,
                'name' => 'Secretary',
                'deleted' => NULL,
            ],
            [
                'id' => 201,
                'name' => 'Kingdom Earl Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 202,
                'name' => 'Kingdom Rapier Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 203,
                'name' => 'Kingdom Armored Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 204,
                'name' => 'Authorizing Rapier Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 205,
                'name' => 'Authorizing Armored Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 206,
                'name' => 'Authorizing Youth Armored Marshal',
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('roles');
        $table->insert($data)->save();
    }
}