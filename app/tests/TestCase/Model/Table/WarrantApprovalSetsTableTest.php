<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WarrantRostersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WarrantRostersTable Test Case
 */
class WarrantRostersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WarrantRostersTable
     */
    protected $WarrantRosters;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.WarrantRosters',
        'app.WarrantRosterApprovals',
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
        $config = $this->getTableLocator()->exists('WarrantRosters') ? [] : ['className' => WarrantRostersTable::class];
        $this->WarrantRosters = $this->getTableLocator()->get('WarrantRosters', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WarrantRosters);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\WarrantRostersTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
