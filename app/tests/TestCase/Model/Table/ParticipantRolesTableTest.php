<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MemberRolesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MemberRolesTable Test Case
 */
class MemberRolesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\MemberRolesTable
     */
    protected $MemberRoles;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.MemberRoles',
        'app.Member',
        'app.Role',
        'app.AuthorizedBy',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('MemberRoles') ? [] : ['className' => MemberRolesTable::class];
        $this->MemberRoles = $this->getTableLocator()->get('MemberRoles', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->MemberRoles);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\MemberRolesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\MemberRolesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
