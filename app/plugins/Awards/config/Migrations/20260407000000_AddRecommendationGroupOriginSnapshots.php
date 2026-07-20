<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Persist pre-group recommendation state snapshots for safe child restoration.
 */
class AddRecommendationGroupOriginSnapshots extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Add origin snapshot columns and backfill current grouped children.
     *
     * @return void
     */
    public function change(): void
    {
        $this->table('awards_recommendations')
            ->addColumn('group_origin_state', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
                'after' => 'recommendation_group_id',
            ])
            ->addColumn('group_origin_status', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
                'after' => 'group_origin_state',
            ])
            ->update();

        $this->backfillCurrentGroupOrigins();
    }

    /**
     * Backfill current grouped recommendations from the most recent non-linked origin log.
     *
     * @return void
     */
    private function backfillCurrentGroupOrigins(): void
    {
        $groupedRecommendations = $this->fetchAll(
            'SELECT id FROM awards_recommendations ' .
            'WHERE recommendation_group_id IS NOT NULL ' .
            'AND (group_origin_state IS NULL OR group_origin_status IS NULL)',
        );

        foreach ($groupedRecommendations as $row) {
            $recommendationId = (int)$row['id'];
            $originRows = $this->fetchAll(
                "SELECT from_state, from_status
                 FROM awards_recommendations_states_logs
                 WHERE recommendation_id = {$recommendationId}
                   AND to_state IN ('Linked', 'Linked - Closed')
                   AND from_state NOT IN ('Linked', 'Linked - Closed')
                 ORDER BY created DESC, id DESC
                 LIMIT 1",
            );

            if (empty($originRows)) {
                continue;
            }

            $originState = $this->quoteNullableString($originRows[0]['from_state'] ?? null);
            $originStatus = $this->quoteNullableString($originRows[0]['from_status'] ?? null);

            $this->execute(
                "UPDATE awards_recommendations
                 SET group_origin_state = {$originState},
                     group_origin_status = {$originStatus}
                 WHERE id = {$recommendationId}",
            );
        }
    }

    /**
     * Quote nullable string values for small data backfills.
     *
     * @param mixed $value Value to quote.
     * @return string
     */
    private function quoteNullableString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        return '\'' . str_replace('\'', '\'\'', (string)$value) . '\'';
    }
}
