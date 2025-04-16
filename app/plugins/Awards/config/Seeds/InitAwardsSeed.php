<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Role seed.
 */
class InitAwardsSeed extends BaseSeed
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
                'name' => 'Can Manage Awards',
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
                'name' => 'Can View Recommendations',
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
                'name' => 'Can Manage Recommendations',
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
