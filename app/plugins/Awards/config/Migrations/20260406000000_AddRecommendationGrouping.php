<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add recommendation grouping support.
 *
 * - Adds recommendation_group_id (self-referential FK) to awards_recommendations
 *   for grouping multiple recommendations into a single working unit.
 */
class AddRecommendationGrouping extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Add recommendation grouping columns and constraints.
     *
     * @return void
     */
    public function change(): void
    {
        // Add recommendation_group_id to awards_recommendations
        $this->table('awards_recommendations')
            ->addColumn('recommendation_group_id', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => 11,
                'after' => 'id',
            ])
            ->addIndex(['recommendation_group_id'], [
                'name' => 'idx_rec_group_id',
            ])
            ->addForeignKey('recommendation_group_id', 'awards_recommendations', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_rec_group_id',
            ])
            ->update();
    }
}
