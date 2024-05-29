<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MembersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MembersTable Test Case
 */
class MembersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\MembersTable
     */
    protected $Members;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.Members",
        "app.MemberAuthorizationTypes",
        "app.PendingAuthorizations",
        "app.PendingAuthorizationsToApprove",
        "app.Roles",
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("Members")
            ? []
            : ["className" => MembersTable::class];
        $this->Members = $this->getTableLocator()->get("Members", $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Members);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\MembersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test findAuth method
     *
     * @return void
     * @uses \App\Model\Table\MembersTable::findAuth()
     */
    public function testFindAuth(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
