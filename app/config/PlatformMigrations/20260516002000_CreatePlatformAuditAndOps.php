<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreatePlatformAuditAndOps extends AbstractMigration
{
    public function change(): void
    {
        $this->table('audit_events')
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('platform_user_id', 'uuid', ['null' => true])
            ->addColumn('action', 'string', ['limit' => 120])
            ->addColumn('subject_type', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('subject_id', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('user_agent', 'text', ['null' => true])
            ->addColumn('previous_hash', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('event_hash', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['tenant_id', 'created_at'])
            ->addIndex(['platform_user_id', 'created_at'])
            ->addIndex(['action'])
            ->addIndex(['created_at'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('platform_user_id', 'platform_users', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('platform_jobs', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid', ['null' => true])
            ->addColumn('requested_by_platform_user_id', 'uuid', ['null' => true])
            ->addColumn('job_type', 'string', ['limit' => 120])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'queued'])
            ->addColumn('idempotency_key', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('parameters', 'json', ['null' => true])
            ->addColumn('log_uri', 'text', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id', 'job_type'])
            ->addIndex(['status'])
            ->addIndex(['idempotency_key'], ['unique' => true])
            ->addIndex(['created_at'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('requested_by_platform_user_id', 'platform_users', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
