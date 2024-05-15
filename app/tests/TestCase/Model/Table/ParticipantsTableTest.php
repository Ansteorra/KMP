<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ParticipantsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ParticipantsTable Test Case
 */
class ParticipantsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ParticipantsTable
     */
    protected $Participants;

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
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Participants') ? [] : ['className' => ParticipantsTable::class];
        $this->Participants = $this->getTableLocator()->get('Participants', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Participants);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\ParticipantsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test findAuth method
     *
     * @return void
     * @uses \App\Model\Table\ParticipantsTable::findAuth()
     */
    public function testFindAuth(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
