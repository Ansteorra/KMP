<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * App\Controller\AppSettingsController Test Case
 *
 * @uses \App\Controller\AppSettingsController
 */
class AppSettingsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::index()
     */
    public function testIndex(): void
    {
        $this->get('/app-settings');
        $this->assertResponseOk();
        $this->assertResponseContains('App Settings');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::add()
     */
    public function testAdd(): void
    {
        $uniqueName = 'Test.Setting.' . time();
        $data = [
            'name' => $uniqueName,
            'value' => 'Test Value',
        ];

        $this->post('/app-settings/add', $data);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => $uniqueName]);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::edit()
     */
    public function testEdit(): void
    {
        // Use an existing setting from dev_seed_clean.sql
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->find()->where(['name' => 'KMP.ShortSiteTitle'])->first();

        $data = [
            'id' => $appSetting->id,
            'raw_value' => 'EDITED',
        ];

        $this->post('/app-settings/edit/' . $appSetting->id, $data);
        $this->assertResponseOk();

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $updatedAppSetting = $appSettingsTable->find()->where(['name' => 'KMP.ShortSiteTitle'])->first();
        $this->assertEquals($data['raw_value'], $updatedAppSetting->value);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::delete()
     */
    public function testDelete(): void
    {
        // Create a test setting to delete
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $testSetting = $appSettingsTable->newEntity([
            'name' => 'test.delete.controller.' . time(),
            'value' => 'delete-me',
            'required' => false,
        ]);
        $appSettingsTable->save($testSetting);

        $this->post('/app-settings/delete/' . $testSetting->id);
        $this->assertResponseSuccess();

        // Check the record was deleted from the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => $testSetting->name]);
        $this->assertEquals(0, $query->count());
    }
}
