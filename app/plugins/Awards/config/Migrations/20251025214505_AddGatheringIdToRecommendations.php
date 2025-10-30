<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddGatheringIdToRecommendations extends BaseMigration
{
    /**
     * Apply schema changes to add a nullable `gathering_id` column to `awards_recommendations`,
     * create a non-unique index `BY_GATHERING_ID`, and add a foreign key constraint to `gatherings.id`.
     */
    public function change(): void
    {
        $table = $this->table('awards_recommendations');
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
                'constraint' => 'fk_recommendations_gathering_id'
            ]
        );
        $table->update();
    }
}