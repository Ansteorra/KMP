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
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
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
