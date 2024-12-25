<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantPeriodsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantPeriodsTable Test Case
 */
class WarrantPeriodsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantPeriodsTable
     */
    protected $WarrantPeriods;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.WarrantPeriods',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('WarrantPeriods') ? [] : ['className' => WarrantPeriodsTable::class];
        $this->WarrantPeriods = $this->getTableLocator()->get('WarrantPeriods', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WarrantPeriods);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantPeriodsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
