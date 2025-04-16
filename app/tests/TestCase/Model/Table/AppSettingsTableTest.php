<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AppSettingsTable;
use Cake\TestSuite\TestCase;
use Cake\Cache\Cache;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\EntityInterface;

/**
 * App\Model\Table\AppSettingsTable Test Case
 */
class AppSettingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AppSettingsTable
     */
    protected $AppSettings;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.AppSettings"];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("AppSettings")
            ? []
            : ["className" => AppSettingsTable::class];
        $this->AppSettings = $this->getTableLocator()->get(
            "AppSettings",
            $config,
        );

        // Clear cache before each test
        Cache::clear();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->AppSettings);
        // Clear cache after each test
        Cache::clear();

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        // Test valid entity
        $data = [
            'name' => 'test.validation.valid',
            'value' => 'test-value'
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertEmpty($appSetting->getErrors());

        // Test missing required field (name)
        $data = [
            'value' => 'test-value'
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertNotEmpty($appSetting->getErrors());
        $this->assertTrue(isset($appSetting->getErrors()['name']));

        // Test max length validation for name
        $data = [
            'name' => str_repeat('a', 300),
            'value' => 'test-value'
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertNotEmpty($appSetting->getErrors());
        $this->assertTrue(isset($appSetting->getErrors()['name']));
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        // Test unique name constraint
        $data = [
            'name' => 'test.setting.one', // This name already exists in fixtures
            'value' => 'duplicate-test-value'
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertFalse($this->AppSettings->save($appSetting));
        $this->assertNotEmpty($appSetting->getErrors());
        $this->assertTrue(isset($appSetting->getErrors()['name']));
    }

    /**
     * Test save method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::save()
     */
    public function testSave(): void
    {
        $data = [
            'name' => 'test.save.method',
            'value' => 'save-test-value'
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $result = $this->AppSettings->save($appSetting);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals('test.save.method', $result->name);
        $this->assertEquals('save-test-value', $result->value);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::delete()
     */
    public function testDelete(): void
    {
        // Get a test setting
        $appSetting = $this->AppSettings->get(1000);
        $result = $this->AppSettings->delete($appSetting);

        $this->assertTrue($result);

        // Verify it was deleted
        $this->expectException(RecordNotFoundException::class);
        $this->AppSettings->get(1);
    }

    /**
     * Test getSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::getSetting()
     */
    public function testGetSetting(): void
    {
        // Test getting an existing setting
        $value = $this->AppSettings->getSetting('test.setting.one');
        $this->assertEquals('test-value-1', $value);

        // Test getting a non-existent setting
        $value = $this->AppSettings->getSetting('nonexistent.setting');
        $this->assertNull($value);

        // Test cache functionality - first call should populate cache
        $value = $this->AppSettings->getSetting('test.setting.two');
        $this->assertEquals('test-value-2', $value);

        // Modify directly in the database to check if cached value is returned
        $appSetting = $this->AppSettings->get(2000);
        $appSetting->value = 'modified-value';
        $this->AppSettings->save($appSetting);

        // Should return cached value, not updated value
        $cachedValue = $this->AppSettings->getSetting('test.setting.two');
        $this->assertEquals('test-value-2', $cachedValue);

        // Clear cache and test again
        Cache::clear();
        $freshValue = $this->AppSettings->getSetting('test.setting.two');
        $this->assertEquals('modified-value', $freshValue);
    }

    /**
     * Test updateSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::updateSetting()
     */
    public function testUpdateSetting(): void
    {
        // Test updating an existing setting
        $result = $this->AppSettings->updateSetting('test.setting.one', 'string', 'updated-value', false);
        $this->assertTrue($result);

        // Verify the value was updated in the database
        $appSetting = $this->AppSettings->find()->where(['name' => 'test.setting.one'])->first();
        $this->assertEquals('updated-value', $appSetting->value);
        $this->assertEquals('string', $appSetting->type);
        $this->assertFalse($appSetting->required);

        // Test that cache was updated
        $cachedValue = Cache::read('app_setting_test.setting.one', 'default');
        $this->assertEquals('updated-value', $cachedValue);

        // Test creating a new setting via updateSetting
        $result = $this->AppSettings->updateSetting('test.new.setting', 'number', '42', true);
        $this->assertTrue($result);

        // Verify the setting was created
        $newSetting = $this->AppSettings->find()->where(['name' => 'test.new.setting'])->first();
        $this->assertNotNull($newSetting);
        $this->assertEquals('42', $newSetting->value);
        $this->assertEquals('number', $newSetting->type);
        $this->assertTrue($newSetting->required);
    }

    /**
     * Test deleteSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::deleteSetting()
     */
    public function testDeleteSetting(): void
    {
        // Test deleting a regular setting
        $result = $this->AppSettings->deleteSetting('test.setting.two');
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => 'test.setting.two'])->first();
        $this->assertNull($setting);

        // Test that cache was cleared
        $cachedValue = Cache::read('app_setting_test.setting.two', 'default');
        $this->assertNull($cachedValue);

        // Test deleting a required setting - should fail without force
        $result = $this->AppSettings->deleteSetting('test.setting.required');
        $this->assertFalse($result);

        // Verify it was not deleted
        $setting = $this->AppSettings->find()->where(['name' => 'test.setting.required'])->first();
        $this->assertNotNull($setting);

        // Test deleting a required setting with force flag
        $result = $this->AppSettings->deleteSetting('test.setting.required', true);
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => 'test.setting.required'])->first();
        $this->assertNull($setting);

        // Test deleting a non-existent setting - should return false
        $result = $this->AppSettings->deleteSetting('nonexistent.setting');
        $this->assertFalse($result);
    }

    /**
     * Test getAppSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::getAppSetting()
     */
    public function testGetAppSetting(): void
    {
        // Test getting an existing setting
        $value = $this->AppSettings->getAppSetting('test.setting.one');
        $this->assertEquals('test-value-1', $value);

        // Test getting a non-existent setting with default value
        $value = $this->AppSettings->getAppSetting('nonexistent.setting', 'default-value');
        $this->assertEquals('default-value', $value);

        // Verify that the setting was created with default value
        $setting = $this->AppSettings->find()->where(['name' => 'nonexistent.setting'])->first();
        $this->assertNotNull($setting);
        $this->assertEquals('default-value', $setting->value);

        // Test getting a non-existent setting without default - should throw exception
        $this->expectException(\Exception::class);
        $this->AppSettings->getAppSetting('another.nonexistent.setting');
    }

    /**
     * Test setAppSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::setAppSetting()
     */
    public function testSetAppSetting(): void
    {
        // Test setting a value - should be a wrapper for updateSetting
        $result = $this->AppSettings->setAppSetting('test.set.method', 'set-value', 'string', true);
        $this->assertTrue($result);

        // Verify the setting was created
        $setting = $this->AppSettings->find()->where(['name' => 'test.set.method'])->first();
        $this->assertNotNull($setting);
        $this->assertEquals('set-value', $setting->value);
        $this->assertEquals('string', $setting->type);
        $this->assertTrue($setting->required);
    }

    /**
     * Test deleteAppSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::deleteAppSetting()
     */
    public function testDeleteAppSetting(): void
    {
        // Test deleting - should be a wrapper for deleteSetting
        $result = $this->AppSettings->deleteAppSetting('test.setting.one');
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => 'test.setting.one'])->first();
        $this->assertNull($setting);
    }

    /**
     * Test getAllAppSettingsStartWith method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::getAllAppSettingsStartWith()
     */
    public function testGetAllAppSettingsStartWith(): void
    {
        // Test getting all settings with a common prefix
        $settings = $this->AppSettings->getAllAppSettingsStartWith('test.group');

        $this->assertIsArray($settings);
        $this->assertCount(2, $settings);
        $this->assertArrayHasKey('test.group.one', $settings);
        $this->assertArrayHasKey('test.group.two', $settings);
        $this->assertEquals('group-value-1', $settings['test.group.one']);
        $this->assertEquals('group-value-2', $settings['test.group.two']);

        // Test with a prefix that doesn't match any settings
        $settings = $this->AppSettings->getAllAppSettingsStartWith('nonexistent');
        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }
}
