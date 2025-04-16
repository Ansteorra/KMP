<?php

declare(strict_types=1);

use Migrations\BaseSeed;

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
        return [
            [
                'id' => 1,
                'name' => 'Nobility',
                'modified' => '2025-01-02 14:08:14',
                'created' => '2025-01-02 14:08:14',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Seneschallate',
                'modified' => '2025-01-16 17:28:27',
                'created' => '2025-01-02 14:08:26',
                'created_by' => 1,
                'modified_by' => 1096,
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Marshallate',
                'modified' => '2025-01-06 01:15:36',
                'created' => '2025-01-02 14:08:35',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Webministry',
                'modified' => '2025-01-15 02:26:41',
                'created' => '2025-01-02 14:08:48',
                'created_by' => 1,
                'modified_by' => 1073,
                'deleted' => NULL,
            ],
            [
                'id' => 5,
                'name' => 'Arts & Sciences',
                'modified' => '2025-01-02 14:08:56',
                'created' => '2025-01-02 14:08:56',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 6,
                'name' => 'Treasury',
                'modified' => '2025-01-13 20:39:29',
                'created' => '2025-01-02 14:09:38',
                'created_by' => 1,
                'modified_by' => 1073,
                'deleted' => NULL,
            ],
            [
                'id' => 7,
                'name' => 'Chatelaine',
                'modified' => '2025-01-13 20:41:32',
                'created' => '2025-01-02 14:10:13',
                'created_by' => 1,
                'modified_by' => 1096,
                'deleted' => NULL,
            ],
            [
                'id' => 8,
                'name' => 'Historian',
                'modified' => '2025-01-02 14:10:24',
                'created' => '2025-01-02 14:10:24',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 9,
                'name' => 'Chronicler',
                'modified' => '2025-01-02 14:11:02',
                'created' => '2025-01-02 14:11:02',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 10,
                'name' => 'College of Heralds',
                'modified' => '2025-01-02 14:11:34',
                'created' => '2025-01-02 14:11:34',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 11,
                'name' => 'College of Scribes',
                'modified' => '2025-01-02 14:11:54',
                'created' => '2025-01-02 14:11:54',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 12,
                'name' => 'Youth and Family Office',
                'modified' => '2025-01-13 20:04:21',
                'created' => '2025-01-02 16:01:21',
                'created_by' => 1,
                'modified_by' => 1096,
                'deleted' => NULL,
            ],
        ];
    }

    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('officers_departments');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}
