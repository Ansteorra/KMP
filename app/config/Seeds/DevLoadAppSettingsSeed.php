<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime; // Added for created/modified timestamps
use Cake\ORM\TableRegistry; // Added
require_once __DIR__ . '/Lib/SeedHelpers.php'; // Assuming this is a custom helper for getting member IDs

/**
 * AppSettings seed.
 */
class DevLoadAppSettingsSeed extends BaseSeed
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
        $data = $this->getData();
        $table = $this->table('app_settings');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed
        $table->insert($data)->save();
    }

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        // It's assumed that the member referenced by original created_by/modified_by ID 1
        // is the 'Earl@test.com' member from DevLoadMembersSeed.
        // This member must be seeded before this seed runs.
        $memberId = SeedHelpers::getMemberId('admin@test.com');

        return [
            [
                // 'id' => 500, // Removed
                'name' => 'Member.ExternalLink.Order of Precedence',
                'value' => 'https://op.ansteorra.org/people/id/{{additional_info->OrderOfPrecedence_Id}}',
                'modified' => DateTime::now(), // Using current time
                'created' => DateTime::now(), // Using current time
                'created_by' => $memberId,
                'modified_by' => $memberId,
                'required' => false,
                'type' => 'string',
            ],
            [
                // 'id' => 501, // Removed
                'name' => 'Member.AdditionalInfo.OrderOfPrecedence_Id',
                'value' => 'number',
                'modified' => DateTime::now(), // Using current time
                'created' => DateTime::now(), // Using current time
                'created_by' => $memberId,
                'modified_by' => $memberId,
                'required' => false,
                'type' => 'string',
            ]
        ];
    }
}
