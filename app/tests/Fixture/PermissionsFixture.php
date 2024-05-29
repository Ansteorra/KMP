<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PermissionsFixture
 */
class PermissionsFixture extends TestFixture
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
                "authorization_type_id" => 1,
                "require_active_membership" => 1,
                "require_active_background_check" => 1,
                "require_min_age" => 1,
                "system" => 1,
                "is_super_user" => 1,
            ],
        ];
        parent::init();
    }
}
