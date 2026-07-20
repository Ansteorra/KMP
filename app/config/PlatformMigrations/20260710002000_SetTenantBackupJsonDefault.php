<?php
declare(strict_types=1);

// phpcs:disable

use Migrations\AbstractMigration;

class SetTenantBackupJsonDefault extends AbstractMigration
{
    public function up(): void
    {
        $this->table('tenant_backups')
            ->changeColumn('backup_type', 'string', ['limit' => 32, 'default' => 'json'])
            ->update();
    }

    public function down(): void
    {
        $this->table('tenant_backups')
            ->changeColumn('backup_type', 'string', ['limit' => 32, 'default' => 'pg_dump'])
            ->update();
    }
}
