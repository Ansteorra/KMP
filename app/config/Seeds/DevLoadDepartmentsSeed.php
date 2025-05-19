<?php

declare(strict_types=1);

use Migrations\BaseSeed;

require_once __DIR__ . '/Lib/SeedHelpers.php'; // Added
use Cake\I18n\DateTime; // Added

/**
 * Departments seed.
 */
class DevLoadDepartmentsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $adminMemberId = SeedHelpers::getMemberId('admin@test.com'); // Was 1
        $earlMemberId = SeedHelpers::getMemberId('Earl@test.com'); // Was 1096, assuming this is Earl
        $stanMemberId = SeedHelpers::getMemberId('Stan@test.com'); // Was 1073, assuming this is Stan

        return [
            [
                // 'id' => 1, // Removed
                'name' => 'Nobility',
                'modified' => '2025-01-02 14:08:14',
                'created' => '2025-01-02 14:08:14',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 2, // Removed
                'name' => 'Seneschallate',
                'modified' => '2025-01-16 17:28:27',
                'created' => '2025-01-02 14:08:26',
                'created_by' => $adminMemberId,
                'modified_by' => $earlMemberId, // Was 1096
                'deleted' => NULL,
            ],
            [
                // 'id' => 3, // Removed
                'name' => 'Marshallate',
                'modified' => '2025-01-06 01:15:36',
                'created' => '2025-01-02 14:08:35',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 4, // Removed
                'name' => 'Webministry',
                'modified' => '2025-01-15 02:26:41',
                'created' => '2025-01-02 14:08:48',
                'created_by' => $adminMemberId,
                'modified_by' => $stanMemberId, // Was 1073
                'deleted' => NULL,
            ],
            [
                // 'id' => 5, // Removed
                'name' => 'Arts & Sciences',
                'modified' => '2025-01-02 14:08:56',
                'created' => '2025-01-02 14:08:56',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 6, // Removed
                'name' => 'Treasury',
                'modified' => '2025-01-13 20:39:29',
                'created' => '2025-01-02 14:09:38',
                'created_by' => $adminMemberId,
                'modified_by' => $stanMemberId, // Was 1073
                'deleted' => NULL,
            ],
            [
                // 'id' => 7, // Removed
                'name' => 'Chatelaine',
                'modified' => '2025-01-13 20:41:32',
                'created' => '2025-01-02 14:10:13',
                'created_by' => $adminMemberId,
                'modified_by' => $earlMemberId, // Was 1096
                'deleted' => NULL,
            ],
            [
                // 'id' => 8, // Removed
                'name' => 'Historian',
                'modified' => '2025-01-02 14:10:24',
                'created' => '2025-01-02 14:10:24',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 9, // Removed
                'name' => 'Chronicler',
                'modified' => '2025-01-02 14:11:02',
                'created' => '2025-01-02 14:11:02',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 10, // Removed
                'name' => 'College of Heralds',
                'modified' => '2025-01-02 14:11:34',
                'created' => '2025-01-02 14:11:34',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 11, // Removed
                'name' => 'College of Scribes',
                'modified' => '2025-01-02 14:11:54',
                'created' => '2025-01-02 14:11:54',
                'created_by' => $adminMemberId,
                'modified_by' => $adminMemberId,
                'deleted' => NULL,
            ],
            [
                // 'id' => 12, // Removed
                'name' => 'Youth and Family Office',
                'modified' => '2025-01-13 20:04:21',
                'created' => '2025-01-02 16:01:21',
                'created_by' => $adminMemberId,
                'modified_by' => $earlMemberId, // Was 1096
                'deleted' => NULL,
            ],
        ];
    }

    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('officers_departments');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed
        $table->insert($data)->save();
    }
}
