<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;


/**
 * Branches seed.
 */
class InitBranchesSeed extends BaseSeed
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
                //'id' => 1,
                'name' => 'Kingdom',
                'location' => 'Kingdom',
                'parent_id' => NULL,
                'lft' => 1,
                'rght' => 18,
                'created' => DateTime::now(),
                'created_by' => '1',
            ]
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
        $table->insert($data)->saveData();
    }
}