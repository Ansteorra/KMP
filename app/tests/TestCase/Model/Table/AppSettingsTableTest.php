<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AppSettingsTable;
use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use App\Test\TestCase\BaseTestCase;
use Exception;

/**
 * App\Model\Table\AppSettingsTable Test Case
 */
class AppSettingsTableTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AppSettingsTable
     */
    protected $AppSettings;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('AppSettings')
            ? []
            : ['className' => AppSettingsTable::class];
        $this->AppSettings = $this->getTableLocator()->get(
            'AppSettings',
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
            'value' => 'test-value',
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertEmpty($appSetting->getErrors());

        // Test missing required field (name)
        $data = [
            'value' => 'test-value',
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $this->assertNotEmpty($appSetting->getErrors());
        $this->assertTrue(isset($appSetting->getErrors()['name']));

        // Test max length validation for name
        $data = [
            'name' => str_repeat('a', 300),
            'value' => 'test-value',
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
        // Test unique name constraint - use an existing setting from dev_seed_clean.sql
        $data = [
            'name' => 'KMP.KingdomName', // This name already exists in dev_seed_clean.sql
            'value' => 'duplicate-test-value',
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
        // Use a unique name with timestamp to avoid conflicts
        $uniqueName = 'test.save.method.' . time() . rand(1000, 9999);
        $data = [
            'name' => $uniqueName,
            'value' => 'save-test-value',
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $result = $this->AppSettings->save($appSetting);

        $this->assertNotFalse($result, 'Save failed: ' . json_encode($appSetting->getErrors()));
        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals($uniqueName, $result->name);
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
        // Create a test setting to delete
        $data = [
            'name' => 'test.delete.setting',
            'value' => 'delete-test-value',
        ];
        $appSetting = $this->AppSettings->newEntity($data);
        $appSetting = $this->AppSettings->save($appSetting);
        $savedId = $appSetting->id;

        $result = $this->AppSettings->delete($appSetting);
        $this->assertTrue($result);

        // Verify it was deleted
        $this->expectException(RecordNotFoundException::class);
        $this->AppSettings->get($savedId);
    }

    /**
     * Test getSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::getSetting()
     */
    public function testGetSetting(): void
    {
        // Test getting an existing setting from dev_seed_clean.sql
        $value = $this->AppSettings->getSetting('KMP.KingdomName');
        $this->assertEquals('Ansteorra', $value);

        // Test getting a non-existent setting
        $value = $this->AppSettings->getSetting('nonexistent.setting.' . time());
        $this->assertNull($value);

        // Test cache functionality - create a test setting with unique name
        $uniqueName = 'test.cache.setting.' . time() . rand(1000, 9999);
        $testSetting = $this->AppSettings->newEntity([
            'name' => $uniqueName,
            'value' => 'cached-value',
        ]);
        $this->AppSettings->save($testSetting);

        // Clear cache before test
        Cache::clear();

        // First call should populate cache
        $value = $this->AppSettings->getSetting($uniqueName);
        $this->assertEquals('cached-value', $value);

        // Modify directly in the database bypassing the model
        $connection = $this->AppSettings->getConnection();
        $connection->update(
            'app_settings',
            ['value' => 'modified-value'],
            ['name' => $uniqueName]
        );

        // Should return cached value, not updated value
        $cachedValue = $this->AppSettings->getSetting($uniqueName);
        $this->assertEquals('cached-value', $cachedValue);

        // Clear cache and test again
        Cache::clear();
        $freshValue = $this->AppSettings->getSetting($uniqueName);
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
        // Create a temporary unique setting for testing (don't pollute seeded data)
        $tempKey = 'KMP.TestShortSiteTitle';
        $this->AppSettings->newEntity([
            'name' => $tempKey,
            'value' => 'ORIGINAL',
            'type' => 'string',
            'required' => false,
        ]);
        $result = $this->AppSettings->updateSetting($tempKey, 'string', 'ORIGINAL', false);
        $this->assertTrue($result);

        // Test updating the temporary setting
        $result = $this->AppSettings->updateSetting($tempKey, 'string', 'TEST', false);
        $this->assertTrue($result);

        // Verify the value was updated in the database
        $appSetting = $this->AppSettings->find()->where(['name' => $tempKey])->first();
        $this->assertEquals('TEST', $appSetting->value);
        $this->assertEquals('string', $appSetting->type);

        // Test that cache was updated
        $cachedValue = Cache::read('app_setting_' . $tempKey, 'default');
        $this->assertEquals('TEST', $cachedValue);

        // Clean up: delete the temporary setting
        $this->AppSettings->deleteSetting($tempKey);

        // Test creating a new setting via updateSetting
        $result = $this->AppSettings->updateSetting('test.new.setting', 'number', '42', true);
        $this->assertTrue($result);

        // Verify the setting was created
        $newSetting = $this->AppSettings->find()->where(['name' => 'test.new.setting'])->first();
        $this->assertNotNull($newSetting);
        $this->assertEquals('42', $newSetting->value);
        $this->assertEquals('number', $newSetting->type);
        $this->assertTrue($newSetting->required);

        // Clean up: delete the test setting
        $this->AppSettings->deleteSetting('test.new.setting');
    }

    /**
     * Test deleteSetting method
     *
     * @return void
     * @uses \App\Model\Table\AppSettingsTable::deleteSetting()
     */
    public function testDeleteSetting(): void
    {
        // Create a test setting to delete with unique name
        $uniqueName = 'test.delete.regular.' . time() . rand(1000, 9999);
        $testSetting = $this->AppSettings->newEntity([
            'name' => $uniqueName,
            'value' => 'delete-me',
            'required' => false,
        ]);
        $this->AppSettings->save($testSetting);

        // Test deleting a regular setting
        $result = $this->AppSettings->deleteSetting($uniqueName);
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => $uniqueName])->first();
        $this->assertNull($setting);

        // Test that cache was cleared
        $cachedValue = Cache::read('app_setting_' . $uniqueName, 'default');
        $this->assertNull($cachedValue);

        // Create a required setting to test deletion protection with unique name
        $requiredName = 'test.setting.required.' . time() . rand(1000, 9999);
        $requiredSetting = $this->AppSettings->newEntity([
            'name' => $requiredName,
            'value' => 'required-value',
            'required' => true,
        ]);
        $saved = $this->AppSettings->save($requiredSetting);
        $this->assertNotFalse($saved, 'Failed to save required setting: ' . json_encode($requiredSetting->getErrors()));

        // Verify it was saved with required=true
        $verifySettings = $this->AppSettings->find()->where(['name' => $requiredName])->first();
        $this->assertNotNull($verifySettings);
        $this->assertTrue((bool)$verifySettings->required, 'Setting was not marked as required');

        // Test deleting a required setting - should fail without force
        $result = $this->AppSettings->deleteSetting($requiredName);
        $this->assertFalse($result, 'Required setting was deleted without force flag!');

        // Verify it was not deleted
        $setting = $this->AppSettings->find()->where(['name' => $requiredName])->first();
        $this->assertNotNull($setting);

        // Test deleting a required setting with force flag
        $result = $this->AppSettings->deleteSetting($requiredName, true);
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => $requiredName])->first();
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
        // Test getting an existing setting from dev_seed_clean.sql
        $value = $this->AppSettings->getAppSetting('KMP.KingdomName');
        $this->assertEquals('Ansteorra', $value);

        // Test getting a non-existent setting with default value
        $value = $this->AppSettings->getAppSetting('test.nonexistent.setting', 'default-value');
        $this->assertEquals('default-value', $value);

        // Verify that the setting was created with default value
        $setting = $this->AppSettings->find()->where(['name' => 'test.nonexistent.setting'])->first();
        $this->assertNotNull($setting);
        $this->assertEquals('default-value', $setting->value);

        // Test getting a non-existent setting without default - should throw exception
        $this->expectException(Exception::class);
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
        // Create a test setting to delete
        $testSetting = $this->AppSettings->newEntity([
            'name' => 'test.delete.app.setting',
            'value' => 'delete-me',
            'required' => false,
        ]);
        $this->AppSettings->save($testSetting);

        // Test deleting - should be a wrapper for deleteSetting
        $result = $this->AppSettings->deleteAppSetting('test.delete.app.setting');
        $this->assertTrue($result);

        // Verify it was deleted
        $setting = $this->AppSettings->find()->where(['name' => 'test.delete.app.setting'])->first();
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
        // Test getting all settings with a common prefix from dev_seed_clean.sql
        $settings = $this->AppSettings->getAllAppSettingsStartWith('KMP.');

        $this->assertIsArray($settings);
        $this->assertGreaterThan(0, count($settings));
        $this->assertArrayHasKey('KMP.KingdomName', $settings);
        $this->assertArrayHasKey('KMP.ShortSiteTitle', $settings);
        $this->assertEquals('Ansteorra', $settings['KMP.KingdomName']);

        // Test with a prefix that doesn't match any settings
        $settings = $this->AppSettings->getAllAppSettingsStartWith('nonexistent');
        $this->assertIsArray($settings);
        $this->assertEmpty($settings);
    }
}
