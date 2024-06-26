<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AwardsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AwardsTable Test Case
 */
class AwardsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AwardsTable
     */
    protected $Awards;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Awards') ? [] : ['className' => AwardsTable::class];
        $this->Awards = $this->getTableLocator()->get('Awards', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Awards);

        parent::tearDown();
    }
}
