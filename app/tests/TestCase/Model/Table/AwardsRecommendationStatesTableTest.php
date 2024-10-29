<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AwardsRecommendationStatesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AwardsRecommendationStatesTable Test Case
 */
class AwardsRecommendationStatesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AwardsRecommendationStatesTable
     */
    protected $AwardsRecommendationStates;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('AwardsRecommendationStates') ? [] : ['className' => AwardsRecommendationStatesTable::class];
        $this->AwardsRecommendationStates = $this->getTableLocator()->get('AwardsRecommendationStates', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->AwardsRecommendationStates);

        parent::tearDown();
    }
}
