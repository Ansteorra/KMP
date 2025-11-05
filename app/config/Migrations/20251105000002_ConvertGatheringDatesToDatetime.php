<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Convert gathering start_date and end_date from DATE to DATETIME
 * 
 * This allows gatherings to have specific start and end times, not just dates.
 * Existing DATE values will be preserved with a default time of 00:00:00.
 */
class ConvertGatheringDatesToDatetime extends BaseMigration
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

        // Change start_date from DATE to DATETIME
        // Existing dates will be converted to datetime with 00:00:00 time
        $table->changeColumn('start_date', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'Start date and time of the gathering (stored in UTC)'
        ]);

        // Change end_date from DATE to DATETIME
        $table->changeColumn('end_date', 'datetime', [
            'default' => null,
            'null' => false,
            'comment' => 'End date and time of the gathering (stored in UTC)'
        ]);

        $table->update();
    }
}
