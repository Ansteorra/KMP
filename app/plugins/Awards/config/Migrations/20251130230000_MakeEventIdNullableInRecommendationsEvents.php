<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Make event_id nullable in awards_recommendations_events table
 * 
 * This allows the join table to store either Event or Gathering relationships
 * without requiring both. This is needed because the Gatherings belongsToMany
 * relationship inserts rows with only recommendation_id and gathering_id,
 * leaving event_id as NULL.
 */
class MakeEventIdNullableInRecommendationsEvents extends BaseMigration
{
    /**
     * Change Method.
     *
     * Makes event_id column nullable to support Gatherings-only associations.
     */
    public function change(): void
    {
        $table = $this->table('awards_recommendations_events');
        $table->changeColumn('event_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->update();
    }
}
