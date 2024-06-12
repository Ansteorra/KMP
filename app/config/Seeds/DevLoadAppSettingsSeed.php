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
                'name' => 'Activity.SecretaryEmail',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 3,
                'name' => 'KMP.KingdomName',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 4,
                'name' => 'Activity.SecretaryName',
                'value' => 'please_set',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 5,
                'name' => 'Member.ViewCard.Graphic',
                'value' => 'auth_card_back.gif',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 6,
                'name' => 'Member.ViewCard.HeaderColor',
                'value' => 'gold',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 7,
                'name' => 'Member.AdditionalInfo.OP_People_Id',
                'value' => 'number',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 8,
                'name' => 'Member.ExternalLink.Order of Precedence',
                'value' => 'https://op.ansteorra.org/people/id/{{additional_info->OP_People_Id}}',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('app_settings');
        $table->insert($data)->save();
    }
}