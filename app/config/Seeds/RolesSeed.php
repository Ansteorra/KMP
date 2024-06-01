<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Roles seed.
 */
class RolesSeed extends AbstractSeed
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
                'id' => 1,
                'name' => 'Admin',
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Secretary',
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Kingdom Earl Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Kingdom Rapier Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 5,
                'name' => 'Kingdom Armored Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 6,
                'name' => 'Authorizing Rapier Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 7,
                'name' => 'Authorizing Armored Marshal',
                'deleted' => NULL,
            ],
            [
                'id' => 8,
                'name' => 'Authorizing Youth Armored Marshal',
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('roles');
        $table->insert($data)->save();
    }
}
