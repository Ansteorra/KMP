<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AuthorizationApprovalsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AuthorizationApprovalsTable Test Case
 */
class AuthorizationApprovalsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AuthorizationApprovalsTable
     */
    protected $AuthorizationApprovals;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.AuthorizationApprovals",
        "app.Authorizations",
        "app.Members",
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("AuthorizationApprovals")
            ? []
            : ["className" => AuthorizationApprovalsTable::class];
        $this->AuthorizationApprovals = $this->getTableLocator()->get(
            "AuthorizationApprovals",
            $config,
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->AuthorizationApprovals);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\AuthorizationApprovalsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\AuthorizationApprovalsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
