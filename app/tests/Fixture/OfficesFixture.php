<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OfficesFixture
 */
class OfficesFixture extends TestFixture
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
                'department_id' => 1,
                'requires_warrant' => 1,
                'obly_one_per_branch' => 1,
                'deputy_to_id' => 1,
                'grants_role_id' => 1,
                'term_length' => 1,
                'deleted' => '2024-06-05',
            ],
        ];
        parent::init();
    }
}
