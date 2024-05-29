<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\RoleController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\RoleController Test Case
 *
 * @uses \App\Controller\RoleController
 */
class RoleControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.Role"];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\RoleController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\RoleController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\RoleController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\RoleController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\RoleController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
