<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AuthorizationApprovalsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\AuthorizationApprovalsController Test Case
 *
 * @uses \App\Controller\AuthorizationApprovalsController
 */
class AuthorizationApprovalsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.AuthorizationApprovals",
        "app.Authorization",
        "app.Approver",
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\AuthorizationApprovalsController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\AuthorizationApprovalsController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\AuthorizationApprovalsController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\AuthorizationApprovalsController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\AuthorizationApprovalsController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
