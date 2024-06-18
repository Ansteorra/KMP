<?php

declare(strict_types=1);

use App\KMP\KMPMigrationSeedAbstract;
use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Role seed.
 */
class InitActivitiesSeed extends KMPMigrationSeedAbstract
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
                'id' => 11,
                'name' => 'Can Manage Activities',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1',
            ],
            [
                'id' => 12,
                'name' => 'Can Revoke Authorizations',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1',
            ],
            [
                'id' => 13,
                'name' => 'Can Manage Authorization Queues',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1',
            ],
            [
                'id' => 14,
                'name' => 'Can View Activity Reports',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => '1',
            ]
        ];

        $table = $this->table('permissions');
        $table->insert($data)->save();
    }
}