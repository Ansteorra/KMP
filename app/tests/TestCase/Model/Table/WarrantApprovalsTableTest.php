<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantRosterApprovalsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantRosterApprovalsTable Test Case
 */
class WarrantRosterApprovalsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantRosterApprovalsTable
     */
    protected $WarrantRosterApprovals;

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
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('WarrantRosterApprovals') ? [] : ['className' => WarrantRosterApprovalsTable::class];
        $this->WarrantRosterApprovals = $this->getTableLocator()->get('WarrantRosterApprovals', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WarrantRosterApprovals);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantRosterApprovalsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\WarrantRosterApprovalsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
