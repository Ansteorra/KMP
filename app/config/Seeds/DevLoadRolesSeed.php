<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Roles seed.
 */
class DevLoadRolesSeed extends BaseSeed
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
                'id' => 200,
                'name' => 'Secretary',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 201,
                'name' => 'Kingdom Earl Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 202,
                'name' => 'Kingdom Rapier Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 203,
                'name' => 'Kingdom Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 204,
                'name' => 'Authorizing Rapier Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 205,
                'name' => 'Authorizing Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 206,
                'name' => 'Authorizing Youth Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 207,
                'name' => 'User Manager',
                'deleted' => NULL,
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
        $table = $this->table('roles');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}
