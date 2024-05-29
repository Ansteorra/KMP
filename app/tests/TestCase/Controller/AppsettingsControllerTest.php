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
    protected array $fixtures = ["app.Appsettings"];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AppsettingsController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
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
