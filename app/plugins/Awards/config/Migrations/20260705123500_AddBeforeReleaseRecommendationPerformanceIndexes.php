<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddBeforeReleaseRecommendationPerformanceIndexes extends BaseMigration
{
    /**
     * Add hot-path recommendation and state-log indexes.
     *
     * @return void
     */
    public function up(): void
    {
        if ($this->isPostgres()) {
            $this->execute(
                'CREATE INDEX IF NOT EXISTS idx_rec_state_active ' .
                'ON awards_recommendations (state) WHERE deleted IS NULL',
            );
            $this->execute(
                'CREATE INDEX IF NOT EXISTS idx_rec_states_logs_rec_created ' .
                'ON awards_recommendations_states_logs (recommendation_id, created DESC)',
            );

            return;
        }

        $this->table('awards_recommendations')
            ->addIndex(['state'], ['name' => 'idx_rec_state_active'])
            ->update();

        $this->table('awards_recommendations_states_logs')
            ->addIndex(['recommendation_id', 'created'], ['name' => 'idx_rec_states_logs_rec_created'])
            ->update();
    }

    /**
     * Remove hot-path recommendation and state-log indexes.
     *
     * @return void
     */
    public function down(): void
    {
        if ($this->isPostgres()) {
            $this->execute('DROP INDEX IF EXISTS idx_rec_states_logs_rec_created');
            $this->execute('DROP INDEX IF EXISTS idx_rec_state_active');

            return;
        }

        $this->table('awards_recommendations_states_logs')
            ->removeIndexByName('idx_rec_states_logs_rec_created')
            ->update();

        $this->table('awards_recommendations')
            ->removeIndexByName('idx_rec_state_active')
            ->update();
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     *
     * @return bool
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
