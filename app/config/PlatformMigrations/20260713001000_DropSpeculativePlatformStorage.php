<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class DropSpeculativePlatformStorage extends AbstractMigration
{
    /**
     * Remove speculative platform storage that nothing writes or enforces:
     * the releases registry (release gating runs off the deploy manifest),
     * the unused lease-based platform queue (tenant work drains through the
     * tenant-DB queue; ops work through platform_jobs), and the write-only
     * tenants.key_vault_prefix / unenforced tenants.queue_concurrency_limit
     * columns.
     */
    public function up(): void
    {
        $this->table('queue_dead_letter')->drop()->save();
        $this->table('queue_messages')->drop()->save();
        $this->table('releases')->drop()->save();

        $this->table('tenants')
            ->removeColumn('key_vault_prefix')
            ->removeColumn('queue_concurrency_limit')
            ->update();
    }

    /**
     * Restore the dropped storage (empty; nothing ever wrote real data).
     */
    public function down(): void
    {
        $this->table('tenants')
            ->addColumn('key_vault_prefix', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('queue_concurrency_limit', 'integer', ['default' => 5])
            ->update();

        $this->table('releases', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('image_tag', 'string', ['limit' => 255])
            ->addColumn('git_sha', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('min_schema', 'string', ['limit' => 64])
            ->addColumn('max_schema', 'string', ['limit' => 64])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'draft'])
            ->addColumn('manifest', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['image_tag'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['max_schema'])
            ->create();

        $this->table('queue_messages', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('job_class', 'string', ['limit' => 255])
            ->addColumn('payload', 'jsonb')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'queued'])
            ->addColumn('priority', 'integer', ['default' => 100])
            ->addColumn('not_before', 'datetime', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('max_attempts', 'integer', ['default' => 3])
            ->addColumn('locked_by', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('failed_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('producer_schema', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('min_consumer_schema', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('idempotency_key', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['idempotency_key'], ['unique' => true])
            ->addIndex(['tenant_id', 'status', 'priority', 'not_before'])
            ->addIndex(['status', 'locked_until'])
            ->addIndex(['tenant_id', 'locked_until'])
            ->addIndex(['job_class'])
            ->addIndex(['created_at'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('queue_dead_letter', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('original_message_id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('job_class', 'string', ['limit' => 255])
            ->addColumn('payload', 'jsonb')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'dead_letter'])
            ->addColumn('priority', 'integer', ['default' => 100])
            ->addColumn('not_before', 'datetime', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('max_attempts', 'integer', ['default' => 3])
            ->addColumn('locked_by', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('failed_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('producer_schema', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('min_consumer_schema', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('idempotency_key', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('failed_reason', 'text')
            ->addColumn('dead_lettered_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['original_message_id'])
            ->addIndex(['tenant_id', 'job_class'])
            ->addIndex(['tenant_id', 'dead_lettered_at'])
            ->addIndex(['idempotency_key'])
            ->addIndex(['dead_lettered_at'])
            ->addForeignKey('tenant_id', 'tenants', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
