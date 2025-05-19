<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * AwardsLevels seed.
 */
class DevLoadAwardsLevelsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            [
                // 'id' => 1,
                'name' => 'Non-Armigerous',
                'progression_order' => 0,
                'modified' => '2024-06-25 13:53:55',
                'created' => '2024-06-25 13:53:55',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                // 'id' => 2,
                'name' => 'Armigerous',
                'progression_order' => 1,
                'modified' => '2024-06-25 13:54:15',
                'created' => '2024-06-25 13:54:15',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                // 'id' => 3,
                'name' => 'Grant',
                'progression_order' => 2,
                'modified' => '2024-06-25 13:55:21',
                'created' => '2024-06-25 13:55:21',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                // 'id' => 4,
                'name' => 'Peerage',
                'progression_order' => 4,
                'modified' => '2024-06-25 13:56:44',
                'created' => '2024-06-25 13:55:32',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                // 'id' => 5,
                'name' => 'Nobility',
                'progression_order' => 3,
                'modified' => '2024-06-25 13:56:55',
                'created' => '2024-06-25 13:56:55',
                'created_by' => 1,
                'modified_by' => 1,
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
        $table = $this->table('awards_levels');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}