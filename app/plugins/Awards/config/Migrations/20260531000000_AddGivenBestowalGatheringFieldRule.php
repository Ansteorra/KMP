<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Require gathering when a bestowal is marked Given (matches recommendation Given rules).
 */
class AddGivenBestowalGatheringFieldRule extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $stateRow = $this->fetchRow(
            "SELECT id FROM awards_bestowal_states WHERE name = 'Given' LIMIT 1",
        );
        if ($stateRow === false) {
            return;
        }

        $stateId = (int)$stateRow['id'];
        $existing = $this->fetchRow(
            'SELECT id FROM awards_bestowal_state_field_rules
             WHERE state_id = ' . $stateId . "
             AND field_target = 'gathering_id'
             AND rule_type = 'Required'
             LIMIT 1",
        );
        if ($existing !== false) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->table('awards_bestowal_state_field_rules')->insert([
            'state_id' => $stateId,
            'field_target' => 'gathering_id',
            'rule_type' => 'Required',
            'rule_value' => null,
            'created' => $now,
        ]);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $stateRow = $this->fetchRow(
            "SELECT id FROM awards_bestowal_states WHERE name = 'Given' LIMIT 1",
        );
        if ($stateRow === false) {
            return;
        }

        $stateId = (int)$stateRow['id'];
        $this->execute(
            'DELETE FROM awards_bestowal_state_field_rules
             WHERE state_id = ' . $stateId . "
             AND field_target = 'gathering_id'
             AND rule_type = 'Required'",
        );
    }
}
