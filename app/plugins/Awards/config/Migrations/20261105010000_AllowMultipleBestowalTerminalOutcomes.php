<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\Migration\IrreversibleMigrationException;

class AllowMultipleBestowalTerminalOutcomes extends BaseMigration
{
    /** Preserve snapshots; administrators explicitly synchronize open bestowals. */
    public function up(): void
    {
        $this->execute('DROP INDEX IF EXISTS bestowal_template_one_terminal');
        $items = $this->table('awards_bestowal_todo_template_items');
        if (!$items->hasColumn('terminal_status')) {
            $items->addColumn('terminal_status', 'string', ['limit' => 100, 'default' => 'given'])->update();
        }
        $this->table('awards_bestowals')
            ->changeColumn('lifecycle_status', 'string', ['limit' => 100, 'default' => 'open', 'null' => false])
            ->update();

        // This outcome is an Ansteorra default, not a requirement for other kingdoms.
        $this->execute(<<<'SQL'
            INSERT INTO awards_bestowal_todo_template_items
                (template_id, item_key, label, description, assignee_type, assignee_source_id,
                 assignee_source_key, branch_mode, branch_type, is_gating, is_terminal,
                 terminal_status, sort_order, created, modified)
            SELECT item.template_id, 'given_not_received', 'Given Not Received',
                   'Close the bestowal when the award was given but the recipient did not receive it.',
                   item.assignee_type, item.assignee_source_id, item.assignee_source_key,
                   item.branch_mode, item.branch_type, FALSE, TRUE, 'Given Not Received',
                   item.sort_order + 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM awards_bestowal_todo_template_items item
            INNER JOIN awards_bestowal_todo_templates template ON template.id = item.template_id
            WHERE item.item_key = 'given' AND item.is_terminal = TRUE AND item.deleted IS NULL
              AND template.deleted IS NULL
              AND template.name IN ('Kingdom Award', 'Principality Award', 'Baronial Award')
              AND EXISTS (SELECT 1 FROM branches
                          WHERE name = 'Ansteorra' AND type = 'Kingdom' AND deleted IS NULL)
              AND NOT EXISTS (SELECT 1 FROM awards_bestowal_todo_template_items existing
                              WHERE existing.template_id = item.template_id
                                AND existing.item_key = 'given_not_received')
            SQL);
    }

    /** Removing outcomes would discard configured workflow and completion history. */
    public function down(): void
    {
        throw new IrreversibleMigrationException('Bestowal terminal outcomes must be preserved.');
    }
}
