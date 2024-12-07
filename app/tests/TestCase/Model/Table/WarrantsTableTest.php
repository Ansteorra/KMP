<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantsTable Test Case
 */
class WarrantsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantsTable
     */
    protected $Warrants;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Warrants',
        'app.Members',
        'app.WarrantApprovalSets',
        'app.MemberRoles',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Warrants') ? [] : ['className' => WarrantsTable::class];
        $this->Warrants = $this->getTableLocator()->get('Warrants', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Warrants);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\WarrantsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
