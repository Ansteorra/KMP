<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * AwardsDomains seed.
 */
class DevLoadAwardsDomainsSeed extends BaseSeed
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
                'name' => 'Chivalric',
                'modified' => '2024-06-25 15:10:11',
                'created' => '2024-06-25 13:59:24',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Service',
                'modified' => '2024-06-25 13:59:33',
                'created' => '2024-06-25 13:59:33',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Arts & Sciences',
                'modified' => '2024-06-25 13:59:49',
                'created' => '2024-06-25 13:59:49',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Rapier & Steel Weapons',
                'modified' => '2024-06-25 13:59:59',
                'created' => '2024-06-25 13:59:59',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 5,
                'name' => 'Missile Weapons',
                'modified' => '2024-06-25 14:00:13',
                'created' => '2024-06-25 14:00:13',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 6,
                'name' => 'Equestrian',
                'modified' => '2024-06-25 14:00:20',
                'created' => '2024-06-25 14:00:20',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 7,
                'name' => 'Baronial',
                'modified' => '2024-06-25 14:00:36',
                'created' => '2024-06-25 14:00:36',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 8,
                'name' => 'Kingdom',
                'modified' => '2024-06-25 14:00:44',
                'created' => '2024-06-25 14:00:44',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('awards_domains');
        $table->insert($data)->save();
    }
}
