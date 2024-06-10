<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Departments seed.
 */
class DevLoadDepartmentsSeed extends AbstractSeed
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
                'name' => 'Seneschallet',
                'modified' => '2024-06-10 15:32:06',
                'created' => '2024-06-10 15:31:18',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('departments');
        $table->insert($data)->save();
    }
}
