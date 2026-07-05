<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAuthorizationStatusPerformanceIndexes extends BaseMigration
{
    /**
     * Add hot-path authorization status indexes.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('activities_authorizations')
            ->addIndex(['status', 'expires_on'], ['name' => 'idx_auth_status_expires'])
            ->addIndex(['member_id', 'status'], ['name' => 'idx_auth_member_status'])
            ->update();
    }

    /**
     * Remove hot-path authorization status indexes.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('activities_authorizations')
            ->removeIndexByName('idx_auth_member_status')
            ->removeIndexByName('idx_auth_status_expires')
            ->update();
    }
}
