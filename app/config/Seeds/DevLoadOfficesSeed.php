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
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Deputy Seneschal',
                'department_id' => 1,
                'requires_warrant' => 0,
                'required_office' => 0,
                'only_one_per_branch' => 0,
                'deputy_to_id' => 1,
                'grants_role_id' => NULL,
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Earl Marshal',
                'department_id' => 2,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'kingdom_only' => 1,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Rapier Marshal',
                'department_id' => 2,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 5,
                'name' => 'Chivalric Marshal',
                'department_id' => 2,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 6,
                'name' => 'Hospitaller',
                'department_id' => 3,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 7,
                'name' => 'Chronicler',
                'department_id' => 4,
                'requires_warrant' => 1,
                'required_office' => 1,
                'only_one_per_branch' => 1,
                'deputy_to_id' => NULL,
                'grants_role_id' => NULL,
                'kingdom_only' => 0,
                'term_length' => 2,
                'modified' => '2024-06-10 15:32:53',
                'created' => '2024-06-10 15:32:53',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('officers_offices');
        $table->insert($data)->save();
    }
}