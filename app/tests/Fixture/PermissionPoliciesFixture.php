<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PermissionPoliciesFixture
 */
class PermissionPoliciesFixture extends TestFixture
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
                'permission_id' => 1,
                'policy_class' => 'Lorem ipsum dolor sit amet',
                'policy_method' => 'Lorem ipsum dolor sit amet',
            ],
        ];
        parent::init();
    }
}
