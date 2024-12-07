<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantApprovalsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantApprovalsTable Test Case
 */
class WarrantApprovalsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantApprovalsTable
     */
    protected $WarrantApprovals;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.WarrantApprovals',
        'app.WarrantApprovalSets',
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
        $config = $this->getTableLocator()->exists('WarrantApprovals') ? [] : ['className' => WarrantApprovalsTable::class];
        $this->WarrantApprovals = $this->getTableLocator()->get('WarrantApprovals', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WarrantApprovals);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantApprovalsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\WarrantApprovalsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
