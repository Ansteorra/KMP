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
     * Add a non-null `color` column to the `gathering_types` table for calendar visualization.
     *
     * The added column is a 7-character string with default value `#0d6efd`, a comment
     * describing it as a hex color code for calendar display, and is positioned after the
     * `description` column.
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