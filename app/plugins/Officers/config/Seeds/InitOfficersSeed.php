<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Role seed.
 */
class InitOfficersSeed extends BaseSeed
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
                "name" => "Can Manage Offices",
                "require_active_membership" => true,
                "require_active_background_check" => false,
                "require_min_age" => 0,
                "is_system" => true,
                "is_super_user" => false,
                "requires_warrant" => true,
                "created_by" => 1,
                "created" => DateTime::now(),
            ],
            [
                "name" => "Can Manage Officers",
                "require_active_membership" => true,
                "require_active_background_check" => false,
                "require_min_age" => 0,
                "is_system" => true,
                "is_super_user" => false,
                "requires_warrant" => true,
                "created_by" => 1,
                "created" => DateTime::now(),
            ],
            [
                "name" => "Can Manage Departments",
                "require_active_membership" => true,
                "require_active_background_check" => false,
                "require_min_age" => 0,
                "is_system" => true,
                "is_super_user" => false,
                "requires_warrant" => true,
                "created_by" => 1,
                "created" => DateTime::now(),
            ],
            [
                "name" => "Can View Officer Reports",
                "require_active_membership" => true,
                "require_active_background_check" => false,
                "require_min_age" => 0,
                "is_system" => true,
                "is_super_user" => false,
                "requires_warrant" => true,
                "created_by" => 1,
                "created" => DateTime::now(),
            ]
        ];
    }
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}
