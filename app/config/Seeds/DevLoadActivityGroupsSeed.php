<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * ActivityGroups seed.
 */
class DevLoadActivityGroupsSeed extends BaseSeed
{

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $createdByMemberId = SeedHelpers::getMemberId('admin@test.com');

        return [
            [
                //'id' => 1,
                'name' => 'Armored',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                //'id' => 2,
                'name' => 'Rapier',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
            ],
            [
                //'id' => 3,
                'name' => 'Youth Armored',
                'created' => DateTime::now(),
                'created_by' => $createdByMemberId
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
        $table = $this->table('activities_activity_groups');
        // $options = $table->getAdapter()->getOptions();
        // $options['identity_insert'] = true;
        // $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}
