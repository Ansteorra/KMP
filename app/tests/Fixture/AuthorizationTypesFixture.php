<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ActivitiesFixture
 */
class ActivitiesFixture extends TestFixture
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
                "id" => 1,
                "name" => "Lorem ipsum dolor sit amet",
                "term_length" => 1,
                "activity_group_id" => 1,
                "minimum_age" => 1,
                "maximum_age" => 1,
                "num_required_authorizors" => 1,
                "deleted" => "2024-05-23",
            ],
        ];
        parent::init();
    }
}
