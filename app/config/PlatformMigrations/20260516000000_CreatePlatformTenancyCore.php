<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformTenancyCore extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tenants', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('slug', 'string', ['limit' => 80])
            ->addColumn('display_name', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'provisioning'])
            ->addColumn('region', 'string', ['limit' => 64, 'default' => 'us'])
            ->addColumn('primary_host', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('db_server', 'string', ['limit' => 255])
            ->addColumn('db_name', 'string', ['limit' => 255])
            ->addColumn('db_role', 'string', ['limit' => 255])
            ->addColumn('key_vault_prefix', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('schema_version', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('feature_flags', 'json', ['null' => true])
            ->addColumn('tenant_config', 'json', ['null' => true])
            ->addColumn('queue_concurrency_limit', 'integer', ['default' => 5])
            ->addColumn('created_at', 'datetime')
            ->addColumn('activated_at', 'datetime', ['null' => true])
            ->addColumn('suspended_at', 'datetime', ['null' => true])
            ->addColumn('archived_at', 'datetime', ['null' => true])
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['region'])
            ->addIndex(['db_name'], ['unique' => true])
            ->create();

        $this->table('tenant_hosts', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('host', 'string', ['limit' => 255])
            ->addColumn('host_normalized', 'string', ['limit' => 255])
            ->addColumn('is_primary', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'active'])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id'])
            ->addIndex(['host_normalized'], ['unique' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('tenant_secrets_index', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('namespace', 'string', ['limit' => 80])
            ->addColumn('driver', 'string', ['limit' => 40])
            ->addColumn('purpose', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('rotated_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id'])
            ->addIndex(['namespace'])
            ->addIndex(['name'], ['unique' => true])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
