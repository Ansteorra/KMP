<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddQueuedJobsClaimIndex extends BaseMigration
{
    /**
     * Add the worker claim index.
     *
     * @return void
     */
    public function up(): void
    {
        if ($this->isPostgres()) {
            $this->execute(
                'CREATE INDEX IF NOT EXISTS idx_qj_claim ' .
                'ON queued_jobs (job_task, priority, id) WHERE completed IS NULL',
            );

            return;
        }

        $this->table('queued_jobs')
            ->addIndex(['completed', 'job_task', 'priority', 'id'], ['name' => 'idx_qj_claim'])
            ->update();
    }

    /**
     * Remove the worker claim index.
     *
     * @return void
     */
    public function down(): void
    {
        if ($this->isPostgres()) {
            $this->execute('DROP INDEX IF EXISTS idx_qj_claim');

            return;
        }

        $this->table('queued_jobs')
            ->removeIndexByName('idx_qj_claim')
            ->update();
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     *
     * @return bool
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
