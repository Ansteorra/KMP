<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformEscrowTracking extends AbstractMigration
{
    public function change(): void
    {
        $this->table('escrow_ceremonies', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('key_name', 'string', ['limit' => 255])
            ->addColumn('key_version', 'string', ['limit' => 120])
            ->addColumn('threshold', 'integer')
            ->addColumn('share_count', 'integer')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'planned'])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_by_platform_user_id', 'uuid', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['tenant_id', 'key_name', 'key_version'])
            ->addIndex(['status'])
            ->addIndex(['created_at'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('created_by_platform_user_id', 'platform_users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('escrow_share_envelopes', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('escrow_ceremony_id', 'uuid')
            ->addColumn('share_index', 'integer')
            ->addColumn('custodian_label_hash', 'string', ['limit' => 128])
            ->addColumn('envelope_label_hash', 'string', ['limit' => 128])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'sealed'])
            ->addColumn('verified_at', 'datetime', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['escrow_ceremony_id'])
            ->addIndex(['escrow_ceremony_id', 'share_index'], ['unique' => true])
            ->addIndex(['status'])
            ->addForeignKey('escrow_ceremony_id', 'escrow_ceremonies', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('escrow_verifications', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('escrow_ceremony_id', 'uuid', ['null' => true])
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('key_name', 'string', ['limit' => 255])
            ->addColumn('key_version', 'string', ['limit' => 120])
            ->addColumn('threshold', 'integer')
            ->addColumn('share_count', 'integer')
            ->addColumn('verified_at', 'datetime')
            ->addColumn('verified_by_platform_user_id', 'uuid', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['escrow_ceremony_id'])
            ->addIndex(['tenant_id', 'key_name', 'key_version'])
            ->addIndex(['verified_at'])
            ->addIndex(['status'])
            ->addForeignKey('escrow_ceremony_id', 'escrow_ceremonies', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('verified_by_platform_user_id', 'platform_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
