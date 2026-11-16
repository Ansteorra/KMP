<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddLeadBestowalTerminalTodo extends BaseMigration
{
    /** Select one entry point while preserving all configured outcome definitions. */
    public function up(): void
    {
        $this->table('awards_bestowal_todo_template_items')
            ->addColumn('is_terminal_lead', 'boolean', ['default' => false])
            ->update();
        $this->execute(<<<'SQL'
            UPDATE awards_bestowal_todo_template_items SET is_terminal_lead = TRUE
            WHERE id IN (
                SELECT DISTINCT ON (template_id) id
                FROM awards_bestowal_todo_template_items
                WHERE is_terminal = TRUE AND deleted IS NULL
                ORDER BY template_id, (item_key = 'given') DESC, sort_order, id
            )
            SQL);
        $this->execute('CREATE UNIQUE INDEX bestowal_template_one_terminal_lead ON '
            . 'awards_bestowal_todo_template_items (template_id) '
            . 'WHERE is_terminal_lead = TRUE AND deleted IS NULL');
    }

    /** Existing ActionItem snapshots remain intact. */
    public function down(): void
    {
        $this->execute('DROP INDEX bestowal_template_one_terminal_lead');
        $this->table('awards_bestowal_todo_template_items')->removeColumn('is_terminal_lead')->update();
    }
}
