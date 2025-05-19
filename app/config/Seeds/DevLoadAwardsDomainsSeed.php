<?php

declare(strict_types=1);

use Migrations\BaseSeed;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * AwardsDomains seed.
 */
class DevLoadAwardsDomainsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $adminId = SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'name' => 'Chivalric',
                'modified' => '2024-06-25 15:10:11',
                'created' => '2024-06-25 13:59:24',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Service',
                'modified' => '2024-06-25 13:59:33',
                'created' => '2024-06-25 13:59:33',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Arts & Sciences',
                'modified' => '2024-06-25 13:59:49',
                'created' => '2024-06-25 13:59:49',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Rapier & Steel Weapons',
                'modified' => '2024-06-25 13:59:59',
                'created' => '2024-06-25 13:59:59',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Missile Weapons',
                'modified' => '2024-06-25 14:00:13',
                'created' => '2024-06-25 14:00:13',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Equestrian',
                'modified' => '2024-06-25 14:00:20',
                'created' => '2024-06-25 14:00:20',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Baronial',
                'modified' => '2024-06-25 14:00:36',
                'created' => '2024-06-25 14:00:36',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
            [
                'name' => 'Kingdom',
                'modified' => '2024-06-25 14:00:44',
                'created' => '2024-06-25 14:00:44',
                'created_by' => $adminId,
                'modified_by' => $adminId,
                'deleted' => NULL,
            ],
        ];
    }

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
        $data = $this->getData();
        $table = $this->table('awards_domains');
        $table->insert($data)->save();
    }
}