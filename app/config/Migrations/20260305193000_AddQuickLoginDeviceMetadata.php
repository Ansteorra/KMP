<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class AddQuickLoginDeviceMetadata extends AbstractMigration
{
    private const TABLE = 'member_quick_login_devices';

    public function up(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $table = $this->table(self::TABLE);
        $table
            ->addColumn('configured_ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'after' => 'pin_hash',
            ])
            ->addColumn('configured_location_hint', 'string', [
                'limit' => 120,
                'null' => true,
                'after' => 'configured_ip_address',
            ])
            ->addColumn('configured_os', 'string', [
                'limit' => 120,
                'null' => true,
                'after' => 'configured_location_hint',
            ])
            ->addColumn('configured_browser', 'string', [
                'limit' => 120,
                'null' => true,
                'after' => 'configured_os',
            ])
            ->addColumn('configured_user_agent', 'string', [
                'limit' => 512,
                'null' => true,
                'after' => 'configured_browser',
            ])
            ->addColumn('last_used_ip_address', 'string', [
                'limit' => 45,
                'null' => true,
                'after' => 'last_used',
            ])
            ->addColumn('last_used_location_hint', 'string', [
                'limit' => 120,
                'null' => true,
                'after' => 'last_used_ip_address',
            ])
            ->update();
    }

    public function down(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $table = $this->table(self::TABLE);
        $table
            ->removeColumn('configured_ip_address')
            ->removeColumn('configured_location_hint')
            ->removeColumn('configured_os')
            ->removeColumn('configured_browser')
            ->removeColumn('configured_user_agent')
            ->removeColumn('last_used_ip_address')
            ->removeColumn('last_used_location_hint')
            ->update();
    }
}
