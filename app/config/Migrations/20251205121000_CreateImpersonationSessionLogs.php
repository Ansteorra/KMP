<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Tracks when impersonation sessions start and end.
 */
class CreateImpersonationSessionLogs extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('impersonation_session_logs');
        $table
            ->addColumn('impersonator_id', 'integer', ['null' => false])
            ->addColumn('impersonated_member_id', 'integer', ['null' => false])
            ->addColumn('event', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('request_url', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('created', 'datetime', ['default' => null, 'null' => false])
            ->addForeignKey('impersonator_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_impersonation_session_impersonator',
            ])
            ->addForeignKey('impersonated_member_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_impersonation_session_impersonated_member',
            ])
            ->addIndex(['created'], ['name' => 'idx_impersonation_session_created'])
            ->create();
    }

    public function down(): void
    {
        $this->table('impersonation_session_logs')->drop()->save();
    }
}
