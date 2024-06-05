<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * ActivityGroups seed.
 */
class DevLoadActivityGroupsSeed extends AbstractSeed
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
                'name' => 'Armored',
            ],
            [
                'id' => 2,
                'name' => 'Rapier',
            ],
            [
                'id' => 3,
                'name' => 'Youth Armored',
            ],
        ];

        $table = $this->table('activity_groups');
        $table->insert($data)->save();
    }
}
