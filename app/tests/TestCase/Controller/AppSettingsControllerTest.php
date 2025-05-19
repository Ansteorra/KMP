<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\AppSettingsController Test Case
 *
 * @uses \App\Controller\AppSettingsController
 */
class AppSettingsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use AuthenticatedTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.AppSettings',
        'app.Members',
        'app.Roles',
        'app.Permissions',
        'app.RolesPermissions',
        'app.MemberRoles',
        'app.Warrants',
        'app.Branches',
    ];

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
        $this->assertResponseContains('Activities.configVersion');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AppSettingsController::add()
     */
    public function testAdd(): void
    {

        $data = [
            'name' => 'Test.Setting',
            'value' => 'Test Value',
        ];

        $this->post('/app-settings/add', $data);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => 'Test.Setting']);
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

        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->find()->where(['name' => 'test.setting.one'])->first();

        $data = [
            'id' => $appSetting->id,
            'raw_value' => 'Updated Value',
        ];

        $this->post('/app-settings/edit/' . $appSetting->id, $data);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $updatedAppSetting = $appSettingsTable->find()->where(['name' => 'test.setting.one'])->first();
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
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $appSetting = $appSettingsTable->find()->where(['name' => 'test.setting.one'])->first();

        $this->post('/app-settings/delete/' . $appSetting->id);
        $this->assertRedirect(['controller' => 'AppSettings', 'action' => 'index']);

        // Check the record was saved to the database
        $appSettingsTable = $this->getTableLocator()->get('AppSettings');
        $query = $appSettingsTable->find()->where(['name' => 'test.setting.one']);
        $this->assertEquals(0, $query->count());
    }
}
