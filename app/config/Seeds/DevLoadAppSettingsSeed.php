<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * AppSettings seed.
 */
class DevLoadAppSettingsSeed extends AbstractSeed
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
                'name' => '_sys_branches_init',
                'value' => 'recovered',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 2,
                'name' => 'Marshalate Secretary Email',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 3,
                'name' => 'Kingdom Name',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 4,
                'name' => 'Marshalate Secretary Name',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 5,
                'name' => 'Marshal Authorization Graphic',
                'value' => 'auth_card_back.gif',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 6,
                'name' => 'Marshal Authorization Header Color',
                'value' => 'gold',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('app_settings');
        $table->insert($data)->save();
    }
}