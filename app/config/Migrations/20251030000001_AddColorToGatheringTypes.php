<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add Color Field to Gathering Types
 *
 * Adds a color field to gathering_types table for calendar visualization.
 * This allows each gathering type to have a distinct color in the calendar view.
 */
class AddColorToGatheringTypes extends BaseMigration
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
        $table = $this->table('gathering_types');

        $table->addColumn('color', 'string', [
            'default' => '#0d6efd',
            'limit' => 7,
            'null' => false,
            'comment' => 'Hex color code for calendar display',
            'after' => 'description'
        ]);

        $table->update();
    }
}
