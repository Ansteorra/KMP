<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Make Scheduled Activity End Time Optional Migration
 * 
 * Modifies the gathering_scheduled_activities table to make the end_datetime
 * column nullable. This allows scheduled activities that only have a start
 * time (like "site opens" or "site closes") without requiring an end time.
 */
class MakeScheduledActivityEndTimeOptional extends AbstractMigration
{
    /**
     * Up Method.
     *
     * Make the end_datetime column nullable to support activities with only a start time.
     * 
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('gathering_scheduled_activities');

        $table->changeColumn('end_datetime', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'When the scheduled activity ends (optional for activities with only start time)'
        ]);

        $table->update();
    }

    /**
     * Down Method.
     *
     * Revert the end_datetime column back to not nullable.
     * 
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('gathering_scheduled_activities');

        $table->changeColumn('end_datetime', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'When the scheduled activity ends'
        ]);

        $table->update();
    }
}
