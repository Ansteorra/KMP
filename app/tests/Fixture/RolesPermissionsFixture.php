<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RolesPermissionsFixture
 */
class RolesPermissionsFixture extends TestFixture
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
                'role_id' => 1,
                'created' => 1717699519,
                'created_by' => 1,
            ],
        ];
        parent::init();
    }
}
