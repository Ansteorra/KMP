<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Branches seed.
 */
class DevLoadBranchesSeed extends BaseSeed
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
                'id' => 2,
                'name' => 'Region 1',
                'location' => 'Part of Kingdom',
                'parent_id' => 1,
                'lft' => 2,
                'rght' => 9,
                'type' => 'Region',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 3,
                'name' => 'Barony 1',
                'location' => 'A Local group',
                'parent_id' => 2,
                'lft' => 3,
                'rght' => 4,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 4,
                'name' => 'Barony 2',
                'location' => 'A Local group 2',
                'parent_id' => 2,
                'lft' => 5,
                'rght' => 6,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 5,
                'name' => 'Region 2',
                'location' => 'Part of Kingdom 2',
                'parent_id' => 1,
                'lft' => 10,
                'rght' => 17,
                'type' => 'Region',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 6,
                'name' => 'Barony 3',
                'location' => 'A Local group 2',
                'parent_id' => 5,
                'lft' => 11,
                'rght' => 14,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 7,
                'name' => 'Shire 1',
                'location' => 'A sub local group 2',
                'parent_id' => 6,
                'lft' => 12,
                'rght' => 13,
                'type' => 'Sponsored Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 8,
                'name' => 'Region 1 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id' => 2,
                'lft' => 7,
                'rght' => 8,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 9,
                'name' => 'Region 2 Kingdom Land',
                'location' => 'Part of Kingdom',
                'parent_id' => 5,
                'lft' => 15,
                'rght' => 16,
                'type' => 'Local Group',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 10,
                'name' => 'Out of Kingdom',
                'location' => 'Out of Kingdom',
                'parent_id' => NULL,
                'lft' => 19,
                'rght' => 20,
                'type' => 'N/A',
                'created' => DateTime::now(),
                'created_by' => '1'
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
        $table = $this->table('branches');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();

        //update kingdom to add type
        $branchTbl = \Cake\ORM\TableRegistry::getTableLocator()->get('branches');
        $branch = $branchTbl->get(1);
        $branch->type = 'Kingdom';
        $branchTbl->save($branch);
    }
}
