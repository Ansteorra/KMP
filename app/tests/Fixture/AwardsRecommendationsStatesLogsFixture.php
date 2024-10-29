<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AwardsRecommendationsStatesLogsFixture
 */
class AwardsRecommendationsStatesLogsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'recommendation_id' => 1,
                'from_state' => 'Lorem ipsum dolor sit amet',
                'to_state' => 'Lorem ipsum dolor sit amet',
                'created' => '2024-10-20 13:49:28',
                'created_by' => 1,
            ],
        ];
        parent::init();
    }
}
