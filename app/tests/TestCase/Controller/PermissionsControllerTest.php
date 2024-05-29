<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\PermissionsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\PermissionsController Test Case
 *
 * @uses \App\Controller\PermissionsController
 */
class PermissionsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.Permissions",
        "app.AuthorizationTypes",
        "app.Roles",
        "app.RolesPermissions",
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\PermissionsController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\PermissionsController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\PermissionsController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\PermissionsController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\PermissionsController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
