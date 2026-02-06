<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create service principal infrastructure for API integrations.
 * 
 * Service principals are non-human identities that can be assigned roles
 * and access the API using Bearer token authentication.
 */
class CreateServicePrincipals extends AbstractMigration
{
    public function up(): void
    {
        // Main service principals table
        $this->table('service_principals')
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('client_id', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('client_secret_hash', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('ip_allowlist', 'json', [
                'null' => true,
            ])
            ->addColumn('last_used_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addIndex(['client_id'], [
                'unique' => true,
                'name' => 'idx_service_principals_client_id',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_service_principals_active',
            ])
            ->addForeignKey('created_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_service_principals_created_by',
            ])
            ->addForeignKey('modified_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_service_principals_modified_by',
            ])
            ->create();

        // Service principal role assignments (mirrors member_roles structure)
        $this->table('service_principal_roles')
            ->addColumn('service_principal_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('role_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('branch_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('start_on', 'date', [
                'null' => false,
            ])
            ->addColumn('expires_on', 'date', [
                'null' => true,
            ])
            ->addColumn('entity_type', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => 'Direct Grant',
            ])
            ->addColumn('entity_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('approver_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('revoked_on', 'datetime', [
                'null' => true,
            ])
            ->addColumn('revoker_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addForeignKey('service_principal_id', 'service_principals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_roles_service_principal',
            ])
            ->addForeignKey('role_id', 'roles', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_roles_role',
            ])
            ->addForeignKey('branch_id', 'branches', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_roles_branch',
            ])
            ->addForeignKey('approver_id', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_roles_approver',
            ])
            ->addForeignKey('revoker_id', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_roles_revoker',
            ])
            ->addIndex(['service_principal_id', 'role_id'], [
                'name' => 'idx_sp_roles_principal_role',
            ])
            ->addIndex(['start_on', 'expires_on'], [
                'name' => 'idx_sp_roles_active_window',
            ])
            ->create();

        // API tokens for authentication (supports multiple tokens per service principal)
        $this->table('service_principal_tokens')
            ->addColumn('service_principal_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('token_hash', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('expires_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('last_used_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addForeignKey('service_principal_id', 'service_principals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_tokens_service_principal',
            ])
            ->addForeignKey('created_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_tokens_created_by',
            ])
            ->addIndex(['token_hash'], [
                'unique' => true,
                'name' => 'idx_sp_tokens_hash',
            ])
            ->addIndex(['expires_at'], [
                'name' => 'idx_sp_tokens_expiry',
            ])
            ->create();

        // Audit log for API requests
        $this->table('service_principal_audit_logs')
            ->addColumn('service_principal_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('token_id', 'integer', [
                'null' => true,
            ])
            ->addColumn('action', 'string', [
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('endpoint', 'string', [
                'limit' => 512,
                'null' => false,
            ])
            ->addColumn('http_method', 'string', [
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => true,
            ])
            ->addColumn('request_summary', 'text', [
                'null' => true,
            ])
            ->addColumn('response_code', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])
            ->addForeignKey('service_principal_id', 'service_principals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_audit_service_principal',
            ])
            ->addForeignKey('token_id', 'service_principal_tokens', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_sp_audit_token',
            ])
            ->addIndex(['created'], [
                'name' => 'idx_sp_audit_created',
            ])
            ->addIndex(['service_principal_id', 'created'], [
                'name' => 'idx_sp_audit_principal_created',
            ])
            ->create();
    }

    public function down(): void
    {
        $this->table('service_principal_audit_logs')->drop()->save();
        $this->table('service_principal_tokens')->drop()->save();
        $this->table('service_principal_roles')->drop()->save();
        $this->table('service_principals')->drop()->save();
    }
}
