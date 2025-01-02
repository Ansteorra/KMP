<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\WarrantRosterApprovalsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\WarrantRosterApprovalsController Test Case
 *
 * @uses \App\Controller\WarrantRosterApprovalsController
 */
class WarrantRosterApprovalsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.WarrantRosterApprovals',
        'app.WarrantRosters',
        'app.Members',
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\WarrantRosterApprovalsController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\WarrantRosterApprovalsController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\WarrantRosterApprovalsController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\WarrantRosterApprovalsController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\WarrantRosterApprovalsController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
