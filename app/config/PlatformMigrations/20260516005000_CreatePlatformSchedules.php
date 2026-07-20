<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformSchedules extends AbstractMigration
{
    public function change(): void
    {
        $this->table('platform_schedules', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('cron_expression', 'string', ['limit' => 120])
            ->addColumn('command', 'string', ['limit' => 255])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('tenant_scope', 'string', ['limit' => 40, 'default' => 'platform'])
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('payload', 'json', ['null' => true])
            ->addColumn('options', 'json', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'idle'])
            ->addColumn('last_run_at', 'datetime', ['null' => true])
            ->addColumn('next_run_at', 'datetime', ['null' => true])
            ->addColumn('last_success_at', 'datetime', ['null' => true])
            ->addColumn('last_failure_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['enabled', 'tenant_scope'])
            ->addIndex(['tenant_id'])
            ->addIndex(['next_run_at'])
            ->addIndex(['status'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
