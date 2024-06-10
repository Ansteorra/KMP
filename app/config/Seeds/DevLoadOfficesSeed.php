<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Offices seed.
 */
class DevLoadOfficesSeed extends AbstractSeed
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
                'name' => 'Seneschal',
                'department_id' => 1,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('offices');
        $table->insert($data)->save();
    }
}
