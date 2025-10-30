<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GatheringsGatheringActivitiesFixture
 */
class GatheringsGatheringActivitiesFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            // Example gathering-activity associations can be added here if needed
            // [
            //     'gathering_id' => 1,
            //     'gathering_activity_id' => 1,
            //     'sort_order' => 1,
            //     'created' => '2025-10-23 12:00:00',
            //     'modified' => '2025-10-23 12:00:00',
            //     'created_by' => 1,
            //     'modified_by' => 1,
            // ],
        ];
        parent::init();
    }
}
