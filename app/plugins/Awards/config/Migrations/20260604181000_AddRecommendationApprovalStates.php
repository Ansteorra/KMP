<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddRecommendationApprovalStates extends BaseMigration
{
    private const STATES = [
        'In Approval' => 7,
        'Changes Requested' => 8,
    ];

    /**
     * Add recommendation states used by configurable approval queues.
     *
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $statusRows = $this->fetchAll(
            "SELECT id FROM awards_recommendation_statuses WHERE name = 'In Progress' LIMIT 1",
        );
        if ($statusRows === []) {
            return;
        }

        $statusId = (int)$statusRows[0]['id'];
        $existingStateRows = $this->fetchAll(
            'SELECT id, name FROM awards_recommendation_states',
        );
        $existingStateNames = array_column($existingStateRows, 'id', 'name');
        $statesTable = $this->table('awards_recommendation_states');
        foreach (self::STATES as $stateName => $sortOrder) {
            if (isset($existingStateNames[$stateName])) {
                continue;
            }

            $statesTable->insert([
                'status_id' => $statusId,
                'name' => $stateName,
                'sort_order' => $sortOrder,
                'supports_gathering' => false,
                'is_hidden' => false,
                'created' => $now,
            ]);
        }
        $statesTable->saveData();

        $this->insertMissingTransitions($now);
    }

    /**
     * Remove configurable approval states.
     *
     * @return void
     */
    public function down(): void
    {
        $stateRows = $this->fetchAll(
            'SELECT id, name FROM awards_recommendation_states',
        );
        $stateRows = array_filter(
            $stateRows,
            static fn(array $row): bool => in_array((string)$row['name'], array_keys(self::STATES), true),
        );
        if ($stateRows === []) {
            return;
        }

        $stateIds = implode(', ', array_map(static fn(array $row): int => (int)$row['id'], $stateRows));
        $this->execute(
            'DELETE FROM awards_recommendation_state_transitions '
                . "WHERE from_state_id IN ({$stateIds}) OR to_state_id IN ({$stateIds})",
        );
        $this->execute("DELETE FROM awards_recommendation_states WHERE id IN ({$stateIds})");
    }

    /**
     * Insert missing all-to-all transitions involving all current states.
     *
     * @param string $now Timestamp string.
     * @return void
     */
    private function insertMissingTransitions(string $now): void
    {
        $allRows = $this->fetchAll('SELECT id FROM awards_recommendation_states ORDER BY id');
        $allStateIds = array_map(static fn(array $row): int => (int)$row['id'], $allRows);
        if ($allStateIds === []) {
            return;
        }

        $existingRows = $this->fetchAll(
            'SELECT from_state_id, to_state_id FROM awards_recommendation_state_transitions',
        );
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[(int)$row['from_state_id'] . ':' . (int)$row['to_state_id']] = true;
        }

        $transitionsTable = $this->table('awards_recommendation_state_transitions');
        foreach ($allStateIds as $fromId) {
            foreach ($allStateIds as $toId) {
                if ($fromId === $toId || isset($existing["{$fromId}:{$toId}"])) {
                    continue;
                }

                $transitionsTable->insert([
                    'from_state_id' => $fromId,
                    'to_state_id' => $toId,
                    'created' => $now,
                ]);
            }
        }
        $transitionsTable->saveData();
    }
}
