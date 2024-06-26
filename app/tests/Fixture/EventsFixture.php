<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EventsFixture
 */
class EventsFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'awards_events';
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
                'name' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet',
                'branch_id' => 1,
                'start_date' => '2024-06-24 21:17:44',
                'end_date' => '2024-06-24 21:17:44',
                'modified' => '2024-06-24 21:17:44',
                'created' => '2024-06-24 21:17:44',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => '2024-06-24 21:17:44',
            ],
        ];
        parent::init();
    }
}
