<?php

declare(strict_types=1);


use Migrations\BaseSeed;

/**
 * RolesPermissions seed.
 */
class InitRolesPermissionsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            [
                'permission_id' => 1,
                'role_id' => 1,
                'created_by' => 1,
            ]
        ];

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}
