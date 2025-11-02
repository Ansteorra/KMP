<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GatheringTypeGatheringActivitiesFixture
 */
class GatheringTypeGatheringActivitiesFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'gathering_type_gathering_activities';

    /**
     * Import table definition from database
     *
     * @var array
     */
    public array $import = ['table' => 'gathering_type_gathering_activities'];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            // Tournament type has Armored Combat and Archery, both required
            [
                'id' => 1,
                'gathering_type_id' => 1, // Tournament
                'gathering_activity_id' => 1, // Armored Combat
                'not_removable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 2,
                'gathering_type_id' => 1, // Tournament
                'gathering_activity_id' => 2, // Archery
                'not_removable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            // Practice has Armored Combat (removable) and Arts & Sciences (removable)
            [
                'id' => 3,
                'gathering_type_id' => 2, // Practice
                'gathering_activity_id' => 1, // Armored Combat
                'not_removable' => false,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 4,
                'gathering_type_id' => 2, // Practice
                'gathering_activity_id' => 3, // Arts & Sciences
                'not_removable' => false,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
        ];
        parent::init();
    }
}
