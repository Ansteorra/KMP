<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Branches seed.
 */
class DevLoadBranchesSeed extends AbstractSeed
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
                'id' => 2,
                'name' => 'Region 1',
                'location' => 'Part of Kingdom',
                'parent_id' => 1,
                'lft' => 2,
                'rght' => 9,
            ],
            [
                'id' => 3,
                'name' => 'Barony 1',
                'location' => 'A Local group',
                'parent_id' => 2,
                'lft' => 3,
                'rght' => 4,
            ],
            [
                'id' => 4,
                'name' => 'Barony 2',
                'location' => 'A Local group 2',
                'parent_id' => 2,
                'lft' => 5,
                'rght' => 6,
            ],
            [
                'id' => 5,
                'name' => 'Region 2',
                'location' => 'Part of Kingdom 2',
                'parent_id' => 1,
                'lft' => 10,
                'rght' => 17,
            ],
            [
                'id' => 6,
                'name' => 'Barony 3',
                'location' => 'A Local group 2',
                'parent_id' => 5,
                'lft' => 11,
                'rght' => 14,
            ],
            [
                'id' => 7,
                'name' => 'Shire 1',
                'location' => 'A sub local group 2',
                'parent_id' => 6,
                'lft' => 12,
                'rght' => 13,
            ],
            [
                'id' => 8,
                'name' => 'Region 1 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id' => 2,
                'lft' => 7,
                'rght' => 8,
            ],
            [
                'id' => 9,
                'name' => 'Region 2 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id' => 5,
                'lft' => 15,
                'rght' => 16,
            ],
            [
                'id' => 10,
                'name' => 'Out of Kingdom',
                'location' => 'Out of Kingdom',
                'parent_id' => NULL,
                'lft' => 19,
                'rght' => 20,
            ],
        ];

        $table = $this->table('branches');
        $table->insert($data)->save();
    }
}
