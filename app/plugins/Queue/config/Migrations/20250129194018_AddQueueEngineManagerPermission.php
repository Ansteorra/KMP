<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddQueueEngineManagerPermission extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {
        $this->execute(
            "INSERT INTO permissions (name, require_active_membership, require_active_background_check, require_min_age, is_system, is_super_user, requires_warrant) " .
            "VALUES ('Can Manage Queue Engine', 0, 0, 0, 1, 0, 0)"
        );
    }

    public function down(): void
    {
        $this->execute("DELETE FROM permissions WHERE name = 'Can Manage Queue Engine'");
    }
}