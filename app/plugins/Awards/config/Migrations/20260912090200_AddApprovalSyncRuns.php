<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddApprovalSyncRuns extends BaseMigration
{
    /** Apply reversible schema changes. */
    public function change(): void
    {
        $this->table('awards_approval_sync_runs')
            ->addColumn('approval_process_id', 'integer')
            ->addColumn('actor_id', 'integer')
            ->addColumn('fingerprint', 'string', ['limit' => 64])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'queued'])
            ->addColumn('cursor', 'integer', ['default' => 0])
            ->addColumn('discovery_complete', 'boolean', ['default' => false])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['approval_process_id', 'status'])->create();
        $this->table('awards_approval_sync_items')
            ->addColumn('sync_run_id', 'integer')
            ->addColumn('recommendation_id', 'integer')
            ->addColumn('expected_run_ids', 'json')
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending'])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['sync_run_id', 'recommendation_id'], ['unique' => true])
            ->addIndex(['sync_run_id', 'status'])->create();
    }
}
