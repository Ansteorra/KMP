<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * AddNotRemovableToGatheringsGatheringActivities Migration
 *
 * Adds a `not_removable` column to the gatherings_gathering_activities join table.
 * This flag indicates whether an activity can be removed from a specific gathering.
 * When an activity is added via a gathering type template with `not_removable` set to true,
 * this flag will also be true for that gathering-activity relationship.
 */
class AddNotRemovableToGatheringsGatheringActivities extends BaseMigration
{
    /**
     * Add a `not_removable` column to the gatherings_gathering_activities table.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gatherings_gathering_activities');
        $table->addColumn('not_removable', 'boolean', [
            'default' => false,
            'null' => false,
            'after' => 'sort_order',
            'comment' => 'If true, this activity cannot be removed from this gathering'
        ]);
        $table->addIndex(['not_removable'], ['name' => 'idx_ggact_not_removable']);
        $table->update();
    }
}
