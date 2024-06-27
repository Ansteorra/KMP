<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

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
                'id' => 500,
                'name' => 'Member.ExternalLink.Order of Precedence',
                'value' => 'https://op.ansteorra.org/people/id/{{additional_info->OrderOfPrecedence_Id}}',
                'modified' => '2024-06-27 13:24:42',
                'created' => '2024-06-27 13:24:42',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 501,
                'name' => 'Member.AdditionalInfo.OrderOfPrecedence_Id',
                'value' => 'number',
                'modified' => '2024-06-27 13:25:01',
                'created' => '2024-06-27 13:25:01',
                'created_by' => 1,
                'modified_by' => 1,
            ],
        ];

        $table = $this->table('app_settings');
        $table->insert($data)->save();
    }
}