<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create audit trail table for super user impersonation writes.
 */
class CreateImpersonationActionLogs extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('impersonation_action_logs');
        $table
            ->addColumn('impersonator_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('impersonated_member_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('operation', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('table_name', 'string', [
                'limit' => 191,
                'null' => false,
            ])
            ->addColumn('entity_primary_key', 'string', [
                'limit' => 191,
                'null' => false,
            ])
            ->addColumn('request_method', 'string', [
                'limit' => 10,
                'null' => true,
            ])
            ->addColumn('request_url', 'string', [
                'limit' => 512,
                'null' => true,
            ])
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
            ])
            ->addColumn('metadata', 'text', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => false,
            ])
            ->addForeignKey('impersonator_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_impersonation_logs_impersonator',
            ])
            ->addForeignKey('impersonated_member_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_impersonation_logs_impersonated_member',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_impersonation_logs_created',
            ])
            ->addIndex(['table_name'], [
                'name' => 'idx_impersonation_logs_table',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('impersonation_action_logs')->drop()->save();
    }
}
