<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RecommendationsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RecommendationsTable Test Case
 */
class RecommendationsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\RecommendationsTable
     */
    protected $Recommendations;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Recommendations') ? [] : ['className' => RecommendationsTable::class];
        $this->Recommendations = $this->getTableLocator()->get('Recommendations', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Recommendations);

        parent::tearDown();
    }
}
