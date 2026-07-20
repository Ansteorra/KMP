<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add a direct award link on bestowals, backfilled from the lead recommendation.
 */
class AddAwardIdToBestowals extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('awards_bestowals')) {
            return;
        }

        $table = $this->table('awards_bestowals');
        if (!$table->hasColumn('award_id')) {
            $table->addColumn('award_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
                'after' => 'primary_recommendation_id',
            ]);
            $table->addIndex(['award_id'], [
                'name' => 'idx_bestowals_award_id',
                'unique' => false,
            ]);
            $table->update();
        }

        $this->execute(
            'UPDATE awards_bestowals b
             SET award_id = (
                 SELECT r.award_id
                 FROM awards_recommendations r
                 WHERE r.id = b.primary_recommendation_id
                 LIMIT 1
             )
             WHERE b.award_id IS NULL
               AND b.primary_recommendation_id IS NOT NULL',
        );

        $this->execute(
            'UPDATE awards_bestowals b
             SET award_id = (
                 SELECT r.award_id
                 FROM awards_bestowal_recommendations br
                 INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
                 WHERE br.bestowal_id = b.id
                 ORDER BY br.id ASC
                 LIMIT 1
             )
             WHERE b.award_id IS NULL',
        );

        if (!$this->table('awards_bestowals')->hasForeignKey('award_id')) {
            $this->table('awards_bestowals')
                ->addForeignKey('award_id', 'awards_awards', 'id', [
                    'delete' => 'RESTRICT',
                    'update' => 'CASCADE',
                    'constraint' => 'fk_bestowals_award_id',
                ])
                ->update();
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('awards_bestowals') || !$this->table('awards_bestowals')->hasColumn('award_id')) {
            return;
        }

        $table = $this->table('awards_bestowals');
        if ($table->hasForeignKey('award_id')) {
            $table->dropForeignKey('award_id');
        }
        $table->removeColumn('award_id')->update();
    }
}
