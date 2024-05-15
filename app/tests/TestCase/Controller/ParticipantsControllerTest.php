<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\ParticipantsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\ParticipantsController Test Case
 *
 * @uses \App\Controller\ParticipantsController
 */
class ParticipantsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Participants',
        'app.ParticipantAuthorizationTypes',
        'app.PendingAuthorizations',
        'app.PendingAuthorizationsToApprove',
        'app.Roles',
        'app.ParticipantsRoles',
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\ParticipantsController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\ParticipantsController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\ParticipantsController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\ParticipantsController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\ParticipantsController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
