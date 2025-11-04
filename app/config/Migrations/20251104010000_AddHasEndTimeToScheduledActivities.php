<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add Has End Time Flag to Scheduled Activities Migration
 * 
 * Adds a boolean flag to track whether a scheduled activity has an end time.
 * This allows for better UI/UX when creating activities that only need a start time.
 * Defaults to false (no end time).
 */
class AddHasEndTimeToScheduledActivities extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_scheduled_activities');

        $table->addColumn('has_end_time', 'boolean', [
            'default' => false,
            'null' => false,
            'comment' => 'Whether this scheduled activity has an end time',
            'after' => 'end_datetime'
        ]);

        $table->update();
    }
}
