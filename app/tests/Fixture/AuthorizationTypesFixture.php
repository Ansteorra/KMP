<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuthorizationTypesFixture
 */
class AuthorizationTypesFixture extends TestFixture
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
                'name' => 'Lorem ipsum dolor sit amet',
                'length' => 1,
                'authorization_groups_id' => 1,
                'minimum_age' => 1,
                'maximum_age' => 1,
                'num_required_authorizors' => 1,
                'deleted' => '2024-05-23',
            ],
        ];
        parent::init();
    }
}
