<?php
declare(strict_types=1);

// phpcs:disable

use Migrations\AbstractMigration;

class CreatePlatformBackups extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tenant_backups', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('platform_job_id', 'uuid', ['null' => true])
            ->addColumn('backup_type', 'string', ['limit' => 32, 'default' => 'pg_dump'])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'queued'])
            ->addColumn('object_uri', 'text', ['null' => true])
            ->addColumn('object_size_bytes', 'biginteger', ['null' => true])
            ->addColumn('object_sha256', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('encryption_algorithm', 'string', ['limit' => 64])
            ->addColumn('wrapped_dek', 'text', ['null' => true])
            ->addColumn('wrapped_dek_key_name', 'string', ['limit' => 255])
            ->addColumn('wrapped_dek_key_version', 'string', ['limit' => 120])
            ->addColumn('wrapped_dek_metadata', 'json', ['null' => true])
            ->addColumn('error_summary', 'text', ['null' => true])
            ->addColumn('retention_until', 'datetime', ['null' => true])
            ->addColumn('retention_policy', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['tenant_id', 'created_at'])
            ->addIndex(['tenant_id', 'status'])
            ->addIndex(['platform_job_id'])
            ->addIndex(['retention_until'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('platform_job_id', 'platform_jobs', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
