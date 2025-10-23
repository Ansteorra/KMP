<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * TestSuperUserRolePermissionFixture
 * 
 * Links the TestSuperUser role to the "Is Super User" permission.
 * This ensures the test super user has full system access.
 */
class TestSuperUserRolePermissionFixture extends BaseTestFixture
{
    /**
     * The table this fixture is responsible for
     *
     * @var string
     */
    public string $table = 'roles_permissions';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        // Link TestSuperUser role (ID 2) to "Is Super User" permission (ID 1)
        $this->records = [
            [
                'permission_id' => 1, // Is Super User
                'role_id' => 2, // TestSuperUser role (after Admin)
                'created_by' => 1,
            ]
        ];
        parent::init();
    }
}
