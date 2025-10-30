<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * AddCustomDescriptionToGatheringsGatheringActivities Migration
 *
 * Adds a custom_description field to the gatherings_gathering_activities join table.
 * This allows each gathering to have a custom description for an activity that
 * overrides the default activity description.
 *
 * Example: The "Armored Combat" activity might say "Open practice" at one gathering
 * but "Baronial Championship" at another gathering.
 */
class AddCustomDescriptionToGatheringsGatheringActivities extends BaseMigration
{
    /**
     * Add a nullable `custom_description` text column to the gatherings_gathering_activities table.
     *
     * The column allows per-gathering overrides of an activity's default description. It is added
     * with a default of null, is nullable, includes a descriptive comment, and is positioned after
     * the `sort_order` column before the schema update is applied.
     */
    public function change(): void
    {
        $table = $this->table('gatherings_gathering_activities');

        $table->addColumn('custom_description', 'text', [
            'default' => null,
            'null' => true,
            'comment' => 'Optional custom description that overrides the default activity description for this specific gathering',
            'after' => 'sort_order'
        ]);

        $table->update();
    }
}