<?php

declare(strict_types=1);

use App\KMP\KMPMigrationSeedAbstract;
use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Role seed.
 */
class InitOfficersSeed extends KMPMigrationSeedAbstract
{
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
        $data = [
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

        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}