<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PermissionPoliciesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PermissionPoliciesTable Test Case
 */
class PermissionPoliciesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PermissionPoliciesTable
     */
    protected $PermissionPolicies;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.PermissionPolicies',
        'app.Permissions',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('PermissionPolicies') ? [] : ['className' => PermissionPoliciesTable::class];
        $this->PermissionPolicies = $this->getTableLocator()->get('PermissionPolicies', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->PermissionPolicies);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\PermissionPoliciesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\PermissionPoliciesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
