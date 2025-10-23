<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * AppSettingsFixture
 */
class AppSettingsFixture extends BaseTestFixture
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
                'id' => 1000,
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
                'id' => 2000,
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
                'id' => 3000,
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
                'id' => 4000,
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
                'id' => 5000,
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
                'id' => 6000,
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
        $this->records = array_merge($this->records, $this->getData('DevLoadAppSettingsSeed', null, true));
        //disable plugins via their feature flags
        //if the name starts with "Plugin." and ends with "Active" then set the value to "no"
        foreach ($this->records as $key => $record) {
            if (preg_match('/^Plugin\..*Active$/', $record['name'])) {
                $this->records[$key]['value'] = 'no';
            }
        }
        parent::init();
    }
}
