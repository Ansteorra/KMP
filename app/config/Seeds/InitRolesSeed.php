<?php

declare(strict_types=1);



use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Roles seed.
 */
class InitRolesSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $adminId = SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                'name' => 'Admin',
                'is_system' => true,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'created_by' => $adminId,
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

        $table = $this->table('roles');
        $table->insert($data)->save();
    }
}
