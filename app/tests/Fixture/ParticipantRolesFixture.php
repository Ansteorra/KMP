<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MemberRolesFixture
 */
class MemberRolesFixture extends TestFixture
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
                "Member_id" => 1,
                "role_id" => 1,
                "ended_on" => "2024-05-16",
                "start_on" => "2024-05-16",
                "approver_id" => 1,
            ],
        ];
        parent::init();
    }
}
