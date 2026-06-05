<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveApprovalRecommendationStates extends BaseMigration
{
    private const STATES = [
        'In Approval' => 7,
        'Changes Requested' => 8,
    ];

    /**
     * Remove approval-specific recommendation states now that workflow runs own approval progress.
     *
     * @return void
     */
    public function up(): void
    {
        if (
            !$this->hasTable('awards_recommendations')
            || !$this->hasTable('awards_recommendation_states')
            || !$this->hasTable('awards_recommendation_statuses')
        ) {
            return;
        }

        $this->execute(
            "UPDATE awards_recommendations
             SET state = 'Submitted',
                 status = 'In Progress'
             WHERE state IN ('In Approval', 'Changes Requested')",
        );

        $stateIds = $this->approvalStateIds();
        if ($stateIds === []) {
            return;
        }

        $idList = implode(', ', $stateIds);
        if ($this->hasTable('awards_recommendation_state_transitions')) {
            $this->execute(
                "DELETE FROM awards_recommendation_state_transitions
                 WHERE from_state_id IN ({$idList})
                    OR to_state_id IN ({$idList})",
            );
        }
        if ($this->hasTable('awards_recommendation_state_field_rules')) {
            $this->execute(
                "DELETE FROM awards_recommendation_state_field_rules
                 WHERE state_id IN ({$idList})",
            );
        }
        $this->execute("DELETE FROM awards_recommendation_states WHERE id IN ({$idList})");
    }

    /**
     * Restore the approval-specific recommendation states for rollback.
     *
     * @return void
     */
    public function down(): void
    {
        if (
            !$this->hasTable('awards_recommendation_states')
            || !$this->hasTable('awards_recommendation_statuses')
        ) {
            return;
        }

        $statusRows = $this->fetchAll(
            "SELECT id FROM awards_recommendation_statuses WHERE name = 'In Progress' LIMIT 1",
        );
        if ($statusRows === []) {
            return;
        }

        $statusId = (int)$statusRows[0]['id'];
        $existing = array_column(
            $this->fetchAll('SELECT id, name FROM awards_recommendation_states'),
            'id',
            'name',
        );
        $now = date('Y-m-d H:i:s');
        $statesTable = $this->table('awards_recommendation_states');
        foreach (self::STATES as $stateName => $sortOrder) {
            if (isset($existing[$stateName])) {
                continue;
            }

            $statesTable->insert([
                'status_id' => $statusId,
                'name' => $stateName,
                'sort_order' => $sortOrder,
                'supports_gathering' => false,
                'is_hidden' => false,
                'is_system' => false,
                'created' => $now,
            ]);
        }
        $statesTable->saveData();
        $this->insertMissingTransitions($now);
    }

    /**
     * @return array<int>
     */
    private function approvalStateIds(): array
    {
        $quotedNames = implode(
            ', ',
            array_map(
                static fn(string $state): string => "'" . str_replace("'", "''", $state) . "'",
                array_keys(self::STATES),
            ),
        );
        $rows = $this->fetchAll(
            "SELECT id FROM awards_recommendation_states WHERE name IN ({$quotedNames})",
        );

        return array_map(static fn(array $row): int => (int)$row['id'], $rows);
    }

    /**
     * Insert missing all-to-all transitions involving all current states.
     *
     * @param string $now Timestamp string.
     * @return void
     */
    private function insertMissingTransitions(string $now): void
    {
        if (!$this->hasTable('awards_recommendation_state_transitions')) {
            return;
        }

        $allStateIds = array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->fetchAll('SELECT id FROM awards_recommendation_states ORDER BY id'),
        );
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
