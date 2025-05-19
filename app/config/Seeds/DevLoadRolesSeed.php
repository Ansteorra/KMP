<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

require_once __DIR__ . '/Lib/SeedHelpers.php';

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
        $adminId = SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'name' => 'Secretary',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Kingdom Earl Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Kingdom Rapier Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Kingdom Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Authorizing Rapier Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Authorizing Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'Authorizing Youth Armored Marshal',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'name' => 'User Manager',
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId
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
        $table->insert($data)->save();
    }
}