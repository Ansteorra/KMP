<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\MembersController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\MembersController Test Case
 *
 * @uses \App\Controller\MembersController
 */
class MembersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.Members",
        "app.MemberActivities",
        "app.PendingAuthorizations",
        "app.PendingAuthorizationsToApprove",
        "app.Roles",
        "app.MembersRoles",
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\MembersController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\MembersController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\MembersController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\MembersController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\MembersController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
