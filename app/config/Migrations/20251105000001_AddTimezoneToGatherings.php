<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add timezone column to gatherings table
 * 
 * Allows each gathering to specify its own timezone based on the event location.
 * When null, system will fall back to user timezone or app default.
 */
class AddTimezoneToGatherings extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * 
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gatherings');

        // Add timezone column - nullable to allow falling back to user/app timezone
        $table->addColumn('timezone', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
            'after' => 'location',
            'comment' => 'IANA timezone identifier for the event location (e.g., America/Chicago)'
        ]);

        $table->update();
    }
}