<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMemberQuickLoginDevices extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('member_quick_login_devices');

        $table
            ->addColumn('member_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('device_id', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('pin_hash', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('failed_attempts', 'integer', [
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('last_failed_login', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('last_used', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => false,
            ])
            ->addIndex(['member_id'], [
                'name' => 'idx_mqld_member_id',
            ])
            ->addIndex(['device_id'], [
                'name' => 'idx_mqld_device_id',
            ])
            ->addIndex(['member_id', 'device_id'], [
                'name' => 'idx_mqld_member_device',
                'unique' => true,
            ])
            ->addForeignKey('member_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
