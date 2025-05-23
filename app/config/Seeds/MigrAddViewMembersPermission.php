<?php

declare(strict_types=1);


use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php'; // Added


/**
 * Permissions seed.
 */
class MigrAddViewMembersPermission extends BaseSeed
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
        $adminMemberId = SeedHelpers::getMemberId('admin@test.com'); // Was '1'

        $data = [
            [
                // 'id' => 8, // Removed
                'name' => 'Can View Members',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 0,
                'created' => DateTime::now(),
                'created_by' => $adminMemberId,
            ]

        ];

        $table = $this->table('permissions');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed
        $table->insert($data)->save();
    }
}
