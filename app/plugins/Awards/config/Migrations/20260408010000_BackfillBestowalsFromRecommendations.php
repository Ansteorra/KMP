<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Backfill bestowal records for in-flight recommendations already past crown approval.
 */
class BackfillBestowalsFromRecommendations extends BaseMigration
{
    /**
     * Recommendation state => bestowal state for existing operational records.
     *
     * @var array<string, string>
     */
    private array $stateMap = [
        'Need to Schedule' => 'Created',
        'Scheduled' => 'Court Scheduled',
        'Given' => 'Given',
        'Announced Not Given' => 'Announced Not Given',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('awards_bestowals') || !$this->hasTable('awards_recommendations')) {
            return;
        }

        $rows = $this->fetchAll(
            "SELECT id, member_id, gathering_id, state, status, given, call_into_court,
                    court_availability, person_to_notify, reason,
                    recommendation_group_id, created, created_by, modified_by
             FROM awards_recommendations
             WHERE deleted IS NULL
               AND recommendation_group_id IS NULL
               AND bestowal_id IS NULL
               AND state IN ('Need to Schedule', 'Scheduled', 'Given', 'Announced Not Given')
             ORDER BY id",
        );

        if ($rows === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $recId = (int)$row['id'];
            $bestowalState = $this->stateMap[(string)$row['state']] ?? 'Created';
            if ($bestowalState === 'Created' && !empty($row['gathering_id'])) {
                $bestowalState = 'Gathering Assigned';
            }

            $statusRow = $this->fetchRow(
                "SELECT bs.name AS status_name
                 FROM awards_bestowal_states s
                 INNER JOIN awards_bestowal_statuses bs ON bs.id = s.status_id
                 WHERE s.name = '" . str_replace("'", "''", $bestowalState) . "'
                 LIMIT 1",
            );
            $statusName = $statusRow['status_name'] ?? 'Planning';

            $this->execute(
                "INSERT INTO awards_bestowals (
                    member_id, gathering_id, primary_recommendation_id, status, state,
                    stack_rank, bestowed_at, source, noble_notes, herald_notes,
                    call_into_court, court_availability, person_to_notify, created, modified,
                    created_by, modified_by
                ) VALUES (
                    " . (int)$row['member_id'] . ",
                    " . ($row['gathering_id'] ? (int)$row['gathering_id'] : 'NULL') . ",
                    {$recId},
                    '" . str_replace("'", "''", $statusName) . "',
                    '" . str_replace("'", "''", $bestowalState) . "',
                    0,
                    " . ($row['given'] ? "'" . str_replace("'", "''", (string)$row['given']) . "'" : 'NULL') . ",
                    'recommendation',
                    " . $this->quoteNullable($row['reason'] ?? null) . ",
                    NULL,
                    " . $this->quoteNullable($row['call_into_court']) . ",
                    " . $this->quoteNullable($row['court_availability']) . ",
                    " . $this->quoteNullable($row['person_to_notify']) . ",
                    '" . str_replace("'", "''", (string)($row['created'] ?? $now)) . "',
                    '" . str_replace("'", "''", $now) . "',
                    " . ($row['created_by'] ? (int)$row['created_by'] : 'NULL') . ",
                    " . ($row['modified_by'] ? (int)$row['modified_by'] : 'NULL') . "
                )",
            );

            $bestowalRow = $this->fetchRow('SELECT MAX(id) AS id FROM awards_bestowals');
            $bestowalId = (int)($bestowalRow['id'] ?? 0);
            if ($bestowalId <= 0) {
                continue;
            }

            $this->execute(
                "UPDATE awards_recommendations SET bestowal_id = {$bestowalId} WHERE id = {$recId}",
            );

            $this->execute(
                "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
                 VALUES ({$bestowalId}, {$recId}, '" . str_replace("'", "''", $now) . "')",
            );

            $childRows = $this->fetchAll(
                "SELECT id FROM awards_recommendations
                 WHERE recommendation_group_id = {$recId} AND deleted IS NULL",
            );
            foreach ($childRows as $child) {
                $childId = (int)$child['id'];
                $this->execute(
                    "UPDATE awards_recommendations SET bestowal_id = {$bestowalId} WHERE id = {$childId}",
                );
                $this->execute(
                    "INSERT INTO awards_bestowal_recommendations (bestowal_id, recommendation_id, created)
                     VALUES ({$bestowalId}, {$childId}, '" . str_replace("'", "''", $now) . "')",
                );
            }
        }
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
     * @param mixed $value Nullable string value.
     * @return string SQL literal
     */
    private function quoteNullable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        return "'" . str_replace("'", "''", (string)$value) . "'";
    }
}
