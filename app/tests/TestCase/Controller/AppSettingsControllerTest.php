<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppsettingsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\AppsettingsController Test Case
 *
 * @uses \App\Controller\AppsettingsController
 */
class AppsettingsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.AppSettings", "app.Members", "app.Roles", "app.Permissions", "app.RolesPermissions", "app.MemberRoles", "app.Warrants"];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::index()
     */
    public function testIndex(): void
    {
        //
        $member = $this->getTableLocator()->get('Members')->get(1);
        $this->session([
            'Auth' => [
                'User' => $member
            ]
        ]);
        $this->get('/appsettings');
        $this->assertResponseOk();
        $this->assertResponseContains('App Settings');
        $this->assertResponseContains('KMP.configVersion');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::view()
     */
    public function testView(): void
    {
        $this->get('/appsettings/view/1');
        $this->assertResponseOk();
        $this->assertResponseContains('KMP.configVersion');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::add()
     */
    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $data = [
            'name' => 'Test.Setting',
            'value' => 'Test Value',
        ];

        $this->post('/appsettings/add', $data);
        $this->assertRedirect(['controller' => 'Appsettings', 'action' => 'index']);

        // Check the record was saved to the database
        $query = $this->AppSettings->find()->where(['name' => 'Test.Setting']);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}