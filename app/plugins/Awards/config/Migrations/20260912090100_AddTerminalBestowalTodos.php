<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddTerminalBestowalTodos extends BaseMigration
{
    /** Add terminal configuration without changing existing bestowal snapshots. */
    public function up(): void
    {
        $this->table('awards_bestowal_todo_template_items')
            ->addColumn('is_terminal', 'boolean', ['default' => false])->update();
        $this->execute('UPDATE awards_bestowal_todo_template_items SET is_terminal = '
            . "TRUE WHERE item_key = 'given' AND deleted IS NULL");
        if ($this->getAdapter()->getAdapterType() === 'pgsql') {
            $this->execute('CREATE UNIQUE INDEX bestowal_template_one_terminal ON '
                . 'awards_bestowal_todo_template_items (template_id) WHERE '
                . 'is_terminal = TRUE AND deleted IS NULL');
        } else {
            $this->execute('ALTER TABLE awards_bestowal_todo_template_items ADD '
                . 'terminal_template_id INT GENERATED ALWAYS AS (CASE WHEN '
                . 'is_terminal = TRUE AND deleted IS NULL THEN template_id ELSE '
                . 'NULL END) STORED, ADD UNIQUE INDEX '
                . 'bestowal_template_one_terminal (terminal_template_id)');
        }
    }

    /** Remove the terminal schema. */
    public function down(): void
    {
        if ($this->getAdapter()->getAdapterType() === 'pgsql') {
            $this->execute('DROP INDEX bestowal_template_one_terminal');
        } else {
            $this->execute('ALTER TABLE awards_bestowal_todo_template_items DROP INDEX '
                . 'bestowal_template_one_terminal, DROP COLUMN terminal_template_id');
        }
        $this->table('awards_bestowal_todo_template_items')->removeColumn('is_terminal')->update();
    }
}
