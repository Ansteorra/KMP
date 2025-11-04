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
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gathering_scheduled_activities');

        $table->changeColumn('end_datetime', 'datetime', [
            'default' => null,
            'null' => true,
            'comment' => 'When the scheduled activity ends (optional for activities with only start time)'
        ]);

        $table->update();
    }
}
