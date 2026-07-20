<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Backfill bestowal records for in-flight recommendations already past crown approval.
 */
class BackfillBestowalsFromRecommendations extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('awards_bestowals') || !$this->hasTable('awards_recommendations')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $nowLiteral = $this->quoteString($now);
        $parentCondition = $this->parentRecommendationCondition('r');
        $lifecycleExpression = $this->bestowalLifecycleExpression('r');

        $this->execute(
            "INSERT INTO awards_bestowals (
                member_id, member_sca_name, gathering_id, primary_recommendation_id, lifecycle_status,
                stack_rank, bestowed_at, source, noble_notes, herald_notes,
                call_into_court, court_availability, person_to_notify, created, modified,
                created_by, modified_by
            )
            SELECT
                r.member_id,
                r.member_sca_name,
                r.gathering_id,
                r.id,
                {$lifecycleExpression},
                0,
                r.given,
                'recommendation',
                r.reason,
                NULL,
                r.call_into_court,
                r.court_availability,
                r.person_to_notify,
                COALESCE(r.created, {$nowLiteral}),
                {$nowLiteral},
                r.created_by,
                r.modified_by
            FROM awards_recommendations AS r
            WHERE {$parentCondition}",
        );

        $this->execute(
            "UPDATE awards_recommendations AS r
            SET bestowal_id = (
                SELECT MIN(b.id)
                FROM awards_bestowals AS b
                WHERE b.primary_recommendation_id = r.id
                  AND b.source = 'recommendation'
            )
            WHERE {$parentCondition}",
        );

        $this->execute('DROP TABLE IF EXISTS awards_bestowal_backfill_map');
        $this->execute(
            "CREATE TEMPORARY TABLE awards_bestowal_backfill_map AS
            SELECT r.id AS recommendation_id, r.bestowal_id
            FROM awards_recommendations AS r
            WHERE {$this->parentRecommendationCondition('r', false)}
              AND r.bestowal_id IS NOT NULL",
        );

        $this->execute(
            "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
            SELECT m.bestowal_id, m.recommendation_id, {$nowLiteral}
            FROM awards_bestowal_backfill_map AS m
            WHERE NOT EXISTS (
                SELECT 1
                FROM awards_bestowal_recommendations AS existing
                WHERE existing.bestowal_id = m.bestowal_id
                  AND existing.recommendation_id = m.recommendation_id
            )",
        );

        $this->execute(
            "UPDATE awards_recommendations AS r
            SET bestowal_id = (
                SELECT m.bestowal_id
                FROM awards_bestowal_backfill_map AS m
                WHERE m.recommendation_id = r.recommendation_group_id
            )
            WHERE r.deleted IS NULL
              AND r.recommendation_group_id IS NOT NULL
              AND r.bestowal_id IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM awards_bestowal_backfill_map AS m
                  WHERE m.recommendation_id = r.recommendation_group_id
              )",
        );

        $this->execute(
            "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
            SELECT m.bestowal_id, child.id, {$nowLiteral}
            FROM awards_recommendations AS child
            INNER JOIN awards_bestowal_backfill_map AS m
                ON m.recommendation_id = child.recommendation_group_id
            WHERE child.deleted IS NULL
              AND child.bestowal_id = m.bestowal_id
              AND NOT EXISTS (
                  SELECT 1
                  FROM awards_bestowal_recommendations AS existing
                  WHERE existing.bestowal_id = m.bestowal_id
                    AND existing.recommendation_id = child.id
              )",
        );

        $this->execute('DROP TABLE IF EXISTS awards_bestowal_backfill_map');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('awards_bestowals') || !$this->hasTable('awards_recommendations')) {
            return;
        }

        $this->execute(
            "UPDATE awards_recommendations
             SET bestowal_id = NULL
             WHERE bestowal_id IN (
                 SELECT id FROM awards_bestowals WHERE source = 'recommendation'
             )",
        );
        $this->execute('DELETE FROM awards_bestowal_recommendations');
        $this->execute("DELETE FROM awards_bestowals WHERE source = 'recommendation'");
    }

    /**
     * SQL expression mapping a recommendation's state to a bestowal lifecycle status.
     *
     * Already-given recommendations onboard as `given`; everything still in flight
     * onboards as `open` (parallel to-do checklist drives the rest of the work).
     *
     * @param string $alias Table alias for awards_recommendations.
     * @return string SQL CASE expression.
     */
    private function bestowalLifecycleExpression(string $alias): string
    {
        return "CASE
            WHEN {$alias}.state = 'Given' THEN 'given'
            ELSE 'open'
        END";
    }

    /**
     * WHERE clause for parent recommendations eligible for bestowal backfill.
     *
     * @param string $alias Table alias for awards_recommendations.
     * @param bool $onlyUnlinked Whether to require bestowal_id to be NULL.
     * @return string SQL condition.
     */
    private function parentRecommendationCondition(string $alias, bool $onlyUnlinked = true): string
    {
        $conditions = [
            "{$alias}.deleted IS NULL",
            "{$alias}.recommendation_group_id IS NULL",
            "{$alias}.state IN ('Need to Schedule', 'Scheduled', 'Given', 'Announced Not Given')",
        ];
        if ($onlyUnlinked) {
            $conditions[] = "{$alias}.bestowal_id IS NULL";
        }

        return implode("\n              AND ", $conditions);
    }

    /**
     * Quote a string literal for static migration SQL.
     *
     * @param string $value Value to quote.
     * @return string SQL string literal.
     */
    private function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
