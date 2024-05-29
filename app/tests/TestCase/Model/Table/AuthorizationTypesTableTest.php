<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AuthorizationTypesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AuthorizationTypesTable Test Case
 */
class AuthorizationTypesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AuthorizationTypesTable
     */
    protected $AuthorizationTypes;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        "app.AuthorizationTypes",
        "app.AuthorizationGroups",
        "app.MemberAuthorizationTypes",
        "app.PendingAuthorizations",
        "app.Permissions",
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists("AuthorizationTypes")
            ? []
            : ["className" => AuthorizationTypesTable::class];
        $this->AuthorizationTypes = $this->getTableLocator()->get(
            "AuthorizationTypes",
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
        unset($this->AuthorizationTypes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\AuthorizationTypesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\AuthorizationTypesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete("Not implemented yet.");
    }
}
