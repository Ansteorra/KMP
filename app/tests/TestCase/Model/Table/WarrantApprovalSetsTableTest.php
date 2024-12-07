<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantApprovalSetsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantApprovalSetsTable Test Case
 */
class WarrantApprovalSetsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantApprovalSetsTable
     */
    protected $WarrantApprovalSets;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.WarrantApprovalSets',
        'app.WarrantApprovals',
        'app.Warrants',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('WarrantApprovalSets') ? [] : ['className' => WarrantApprovalSetsTable::class];
        $this->WarrantApprovalSets = $this->getTableLocator()->get('WarrantApprovalSets', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WarrantApprovalSets);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantApprovalSetsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
