<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGridSubscriptions extends BaseMigration
{
    /** Subscription settings are tenant-local; queued jobs contain only an ID and claim token. */
    public function change(): void
    {
        $this->table('grid_subscriptions')
            ->addColumn('member_id', 'integer')
            ->addColumn('grid_key', 'string', ['limit' => 100])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('query_params', 'text')
            ->addColumn('origin', 'string', ['limit' => 255])
            ->addColumn('interval_days', 'integer')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('stop_reason', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('next_run_at', 'datetime')
            ->addColumn('last_sent_at', 'datetime', ['null' => true])
            ->addColumn('claimed_at', 'datetime', ['null' => true])
            ->addColumn('claim_token', 'string', ['limit' => 36, 'null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addForeignKey('member_id', 'members', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['status', 'next_run_at'])
            ->addIndex(['member_id'])
            ->create();
    }
}
