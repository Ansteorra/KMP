<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AppSettingsFixture
 */
class AppSettingsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'test.setting.one',
                'value' => 'test-value-1',
                'type' => null,
                'required' => false,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 2,
                'name' => 'test.setting.two',
                'value' => 'test-value-2',
                'type' => 'string',
                'required' => false,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 3,
                'name' => 'test.setting.required',
                'value' => 'required-value',
                'type' => 'string',
                'required' => true,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 4,
                'name' => 'test.setting.json',
                'value' => '{"key":"value","nested":{"nestedKey":"nestedValue"}}',
                'type' => 'json',
                'required' => false,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 5,
                'name' => 'test.group.one',
                'value' => 'group-value-1',
                'type' => null,
                'required' => false,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 6,
                'name' => 'test.group.two',
                'value' => 'group-value-2',
                'type' => null,
                'required' => false,
                'created' => '2025-04-14 12:00:00',
                'modified' => '2025-04-14 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
        ];
        parent::init();
    }
}
