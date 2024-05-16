<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ParticipantRolesFixture
 */
class ParticipantRolesFixture extends TestFixture
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
                'participant_id' => 1,
                'role_id' => 1,
                'ended_on' => '2024-05-15',
                'start_on' => '2024-05-15',
                'authorized_by_id' => 1,
            ],
        ];
        parent::init();
    }
}
