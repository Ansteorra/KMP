<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuthorizationsFixture
 */
class AuthorizationsFixture extends TestFixture
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
                "member_id" => 1,
                "activity_id" => 1,
                "expires_on" => "2024-05-21",
                "start_on" => "2024-05-21",
            ],
        ];
        parent::init();
    }
}
