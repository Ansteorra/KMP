<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AwardsRecommendationsStatesLogsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AwardsRecommendationsStatesLogsTable Test Case
 */
class AwardsRecommendationsStatesLogsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AwardsRecommendationsStatesLogsTable
     */
    protected $AwardsRecommendationsStatesLogs;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('AwardsRecommendationsStatesLogs') ? [] : ['className' => AwardsRecommendationsStatesLogsTable::class];
        $this->AwardsRecommendationsStatesLogs = $this->getTableLocator()->get('AwardsRecommendationsStatesLogs', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->AwardsRecommendationsStatesLogs);

        parent::tearDown();
    }
}
