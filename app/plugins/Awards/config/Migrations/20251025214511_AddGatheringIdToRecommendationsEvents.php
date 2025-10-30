<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddGatheringIdToRecommendationsEvents extends BaseMigration
{
    /**
     * Apply schema changes to awards_recommendations_events: add a nullable integer `gathering_id` column, an index, and a foreign key constraint.
     *
     * Adds column `gathering_id` (integer, nullable) positioned after `event_id`, creates non-unique index `BY_GATHERING_ID` on `gathering_id`, and adds foreign key `fk_recommendations_events_gathering_id` referencing `gatherings.id` with delete=SET_NULL and update=CASCADE.
     */
    public function change(): void
    {
        $table = $this->table('awards_recommendations_events');
        $table->addColumn('gathering_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
            'after' => 'event_id',
        ]);
        $table->addIndex([
            'gathering_id',
        ], [
            'name' => 'BY_GATHERING_ID',
            'unique' => false,
        ]);
        $table->addForeignKey(
            'gathering_id',
            'gatherings',
            'id',
            [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_recommendations_events_gathering_id'
            ]
        );
        $table->update();
    }
}