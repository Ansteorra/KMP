<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Re-apply cumulative bestowal field rules with Optional + Required milestones.
 */
class ReapplyBestowalCumulativeFieldRules extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $stateRow = $this->fetchRow('SELECT id FROM awards_bestowal_states LIMIT 1');
        if ($stateRow === false) {
            return;
        }

        require_once __DIR__ . '/../bestowal_workflow_field_rules.php';

        $stateNames = array_merge(
            BESTOWAL_LINEAR_PROGRESSION,
            BESTOWAL_COURT_SCHEDULING_ONLY_STATES,
            [BESTOWAL_CANCELLED_STATE],
        );

        $stateRows = $this->fetchAll('SELECT id, name FROM awards_bestowal_states');
        $stateIdMap = [];
        $targetStateIds = [];
        foreach ($stateRows as $row) {
            $stateIdMap[$row['name']] = (int)$row['id'];
            if (in_array($row['name'], $stateNames, true)) {
                $targetStateIds[] = (int)$row['id'];
            }
        }

        if ($targetStateIds === []) {
            return;
        }

        $this->execute(
            'DELETE FROM awards_bestowal_state_field_rules
             WHERE rule_type IN (\'Required\', \'Optional\')
             AND state_id IN (' . implode(',', $targetStateIds) . ')',
        );

        $now = date('Y-m-d H:i:s');
        $rulesTable = $this->table('awards_bestowal_state_field_rules');
        insertBestowalCumulativeFieldRules($stateIdMap, $rulesTable, $now);
        $rulesTable->saveData();
    }

    /**
     * @return void
     */
    public function down(): void
    {
    }
}
