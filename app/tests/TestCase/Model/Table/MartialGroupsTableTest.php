<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AuthorizationGroupsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AuthorizationGroupsTable Test Case
 */
class AuthorizationGroupsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AuthorizationGroupsTable
     */
    protected $AuthorizationGroups;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = ["app.AuthorizationGroups"];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("AuthorizationGroups")
            ? []
            : ["className" => AuthorizationGroupsTable::class];
        $this->AuthorizationGroups = $this->getTableLocator()->get(
            "AuthorizationGroups",
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
        unset($this->AuthorizationGroups);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\AuthorizationGroupsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
