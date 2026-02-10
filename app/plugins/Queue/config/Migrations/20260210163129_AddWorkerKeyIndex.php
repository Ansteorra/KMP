<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddWorkerKeyIndex extends BaseMigration
{
    /**
     * Add index on queued_jobs.workerkey for worker job claim queries.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('queued_jobs')
            ->addIndex(
                [
                    'workerkey',
                ],
                [
                    'name' => 'workerkey',
                ],
            )
            ->update();
    }

    /**
     * Remove workerkey index from queued_jobs.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('queued_jobs')
            ->removeIndex(['workerkey'])
            ->update();
    }
}
